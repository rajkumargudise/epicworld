<?php

namespace App\Services\Editorial;

use App\Enums\AiOperation;
use App\Enums\AiResultStatus;
use App\Enums\ArticleStatus;
use App\Enums\EditorialJobStatus;
use App\Enums\StoryStatus;
use App\Models\Article;
use App\Models\EditorialJob;
use App\Models\Story;
use App\Services\Ai\AiProviderManager;
use App\Services\Ai\AiRequest;
use App\Services\Ai\Exceptions\AiProviderUnavailableException;
use App\Services\Evidence\FactSheet;
use Illuminate\Support\Str;

/**
 * Processes one EditorialJob by asking the configured AI provider to
 * draft an Article from the Story's evidence ledger - the "job
 * started" operation EditorialJobCreator's docblock reserved for the
 * editorial pipeline itself. This is deliberately not wired into
 * stories:discover or any automatic trigger: something has to call
 * generate() explicitly, one job at a time (Milestone 9+ will decide
 * what that something is - a scheduled worker, an admin action, etc).
 *
 * Every AI response is treated as untrusted twice over: once by
 * AiOutputValidator inside the provider adapter (structure/types),
 * and again here, where every claim the model cites as support for
 * the article must appear verbatim in the Story's FactSheet
 * (see FactSheet::supports()). A response with no citations, or with
 * a citation the evidence doesn't support, is rejected outright - the
 * article is not created, and the job fails clearly. This is a
 * conservative first check, not exhaustive fact-checking: it can only
 * catch a citation the model claims but the evidence never said, not
 * every way generated prose could still drift from what a citation
 * literally supports.
 */
class AiArticleGenerator
{
    public const OUTPUT_SCHEMA = [
        'title' => ['type' => 'string', 'max_length' => 180],
        'dek' => ['type' => 'string', 'max_length' => 300, 'required' => false],
        'body' => ['type' => 'string'],
        'key_points' => ['type' => 'array', 'required' => false],
        'tags' => ['type' => 'array', 'required' => false],
        'image_query' => ['type' => 'string', 'max_length' => 80, 'required' => false],
        'citations' => ['type' => 'array'],
    ];

    public function __construct(
        private readonly AiProviderManager $providers,
        private readonly FactExtractor $factExtractor,
        private readonly PublicationDecision $publicationDecision,
    ) {}

    /**
     * A no-op (job left exactly as it was) when the job isn't a
     * pending article_generation job, or its Story isn't currently a
     * Candidate - both mean "nothing to do right now", not a failure.
     * Everything past that point is a real attempt: attempts is
     * incremented, the Story moves to Processing, and every exit path
     * from there either completes the job or fails it, never leaves
     * it hanging.
     */
    public function generate(EditorialJob $job): EditorialJob
    {
        $story = $job->story;

        if ($job->job_type !== EditorialJobCreator::JOB_TYPE_ARTICLE_GENERATION
            || $job->status !== EditorialJobStatus::Pending
            || $story === null
            || $story->status !== StoryStatus::Candidate
        ) {
            return $job;
        }

        $job->update([
            'status' => EditorialJobStatus::Running,
            'attempts' => $job->attempts + 1,
            'started_at' => now(),
            'error' => null,
        ]);
        $story->update(['status' => StoryStatus::Processing]);

        // Give the story its topic (and so its category) from the source's
        // default topic before drafting, so the article lands in a category.
        app(StoryClassifier::class)->classify($story);
        $story->refresh();

        // Re-extract so the evidence ledger reflects every source
        // observation on record right now, not whatever it was when
        // the job was created.
        $this->factExtractor->extract($story);
        $factSheet = $story->refresh()->factSheet();

        if ($factSheet->isEmpty()) {
            return $this->fail($job, $story, 'Story has no supporting facts to draft from.');
        }

        $request = new AiRequest(
            operation: AiOperation::ArticleGeneration,
            storyId: $story->id,
            facts: $factSheet->forAiRequest(),
            instructions: $this->instructions(),
            schema: self::OUTPUT_SCHEMA,
            allowBackground: true,
        );

        $result = $this->providers->resolve()->respond($request);

        // A retired/unknown model (HTTP 404) or a provider outage (HTTP 5xx)
        // is also the provider's problem, not this story's.
        $providerDown = $result->status === AiResultStatus::ProviderError
            && preg_match('/HTTP (404|5\d\d)\b/', (string) $result->error) === 1;

        if ($providerDown || in_array($result->status, [AiResultStatus::RateLimited, AiResultStatus::AuthenticationError, AiResultStatus::Timeout], true)) {
            // Not this story's fault: put everything back as it was so a
            // later run (once quota/credentials are fixed) picks it up.
            $job->update([
                'status' => EditorialJobStatus::Pending,
                'attempts' => max(0, $job->attempts - 1),
                'started_at' => null,
                'error' => sprintf('Waiting - %s (%s): %s', $result->provider, $result->status->value, $result->error),
            ]);
            $story->update(['status' => StoryStatus::Candidate]);

            throw new AiProviderUnavailableException((string) $result->error);
        }

        if (! $result->successful()) {
            return $this->fail(
                $job,
                $story,
                sprintf('%s (%s): %s', $result->provider, $result->status->value, $result->error),
            );
        }

        $citations = $result->data['citations'] ?? [];
        $unsupported = $this->unsupportedCitations($citations, $factSheet);

        if ($citations === [] || $unsupported !== []) {
            return $this->fail($job, $story, $citations === []
                ? 'AI output included no citations to verify against the evidence ledger.'
                : 'AI output cited claims not present in the evidence ledger: '.implode('; ', $unsupported));
        }

        $article = $this->saveArticle($story, $result->data);

        // A free, credited image - best effort, never blocks the article.
        try {
            app(\App\Services\Images\ArticleImageAttacher::class)->attach($article, $result->data['image_query'] ?? null);
        } catch (\Throwable) {
            // an article is never held back for want of an image
        }

        $tagIds = collect((array) ($result->data['tags'] ?? []))
            ->filter(fn ($t) => is_string($t) && trim($t) !== '')
            ->map(fn ($t) => Str::limit(trim(strip_tags($t)), 40, ''))
            ->unique(fn ($t) => Str::slug($t))
            ->take(5)
            ->map(fn ($name) => \App\Models\Tag::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id)
            ->values()
            ->all();

        if ($tagIds !== []) {
            $article->tags()->sync($tagIds);
        }
        // Moves the Article to Review if it passes quality gates, and
        // - as of Milestone 9 - mirrors that onto the Story too. If
        // it doesn't pass, both stay exactly where they are: the
        // Story remains Processing, correctly recording "a draft
        // exists but needs editorial rework", never Review (which
        // would overstate readiness) and never Candidate (which
        // would suggest starting over).
        $this->publicationDecision->decide($article);

        $job->update([
            'status' => EditorialJobStatus::Completed,
            'completed_at' => now(),
            'provider' => $result->provider,
            'model' => $result->model,
            'article_id' => $article->id,
            'output' => $result->data,
        ]);

        return $job->refresh();
    }

    /**
     * A structured instruction, not a hand-assembled prompt containing
     * the evidence itself - AiRequest keeps facts and instructions as
     * separate fields; only the provider adapter combines them into
     * whatever wire format it needs.
     */
    private function instructions(): string
    {
        return 'Write a complete, original, publication-quality news article of 600 to 900 words about this story, in your own words. '
            .'Ground the event itself - who, what, when, where - strictly in the supplied source material: do not include any name, '
            .'number, date, quote or event-specific claim that it does not support, and for each such claim include the exact '
            .'supporting text from the source material in the citations array (a citation the material does not contain will cause '
            .'the whole draft to be rejected). Then turn it into a full article by adding widely known background, definitions and '
            .'context in general terms - what the terms mean, relevant history, why it matters, who is affected, what typically '
            .'happens next, what to watch - without inventing any specifics. Never copy sentences from the source material, and do '
            .'not mention "the source material" in the text. '
            .'Format the body as plain text: a strong opening paragraph (no heading), then 5 to 7 sections that each start with a line '
            .'like "## What happened", "## Background", "## Why it matters", "## Who is affected", "## What happens next", ending with '
            .'"## Bottom line", separated by blank lines. Use "- " lines for lists and a "> " line only for a quote that appears '
            .'verbatim in the source material. Also return: title (clear, under 65 characters, no clickbait), dek (a 140-160 character '
            .'summary sentence), key_points (3 or 4 short standalone takeaway sentences), tags (3 to 5 short topic tags) and '
            .'image_query (2 to 4 plain words describing a generic photograph that could illustrate the story, e.g. "air base runway"; '
            .'never the name of a person).';
    }

    /**
     * @return array<int, string>
     */
    private function unsupportedCitations(mixed $citations, FactSheet $factSheet): array
    {
        if (! is_array($citations)) {
            return ['(citations was not an array)'];
        }

        $unsupported = [];

        foreach ($citations as $citation) {
            // Models sometimes return {"text": "..."} / {"quote": "..."} instead of a plain string.
            if (is_array($citation)) {
                $citation = $citation['text'] ?? $citation['quote'] ?? $citation['evidence'] ?? $citation['claim']
                    ?? (collect($citation)->first(fn ($v) => is_string($v) && trim($v) !== '') ?? null);
            }

            if (! is_string($citation) || ! $factSheet->supportsLoosely($citation)) {
                $unsupported[] = is_string($citation) ? $citation : '(non-string citation)';
            }
        }

        return $unsupported;
    }

    /**
     * Updates the Story's existing Article (e.g. one Milestone 5's
     * ArticleDrafter already created) rather than creating a second
     * one - a Story has at most one Article. The slug is kept if one
     * already exists, since a live draft's URL shouldn't change out
     * from under it.
     *
     * @param  array<string, mixed>  $data
     */
    private function saveArticle(Story $story, array $data): Article
    {
        $article = $story->article ?? new Article(['story_id' => $story->id]);

        $article->fill([
            'category_id' => $story->topic?->category_id,
            'title' => $data['title'],
            'dek' => $data['dek'] ?? null,
            'content' => $data['body'],
            'status' => ArticleStatus::Draft,
            'editorial_metadata' => array_merge($article->editorial_metadata ?? [], [
                'generation_method' => 'ai_assisted',
                'generated_at' => now()->toIso8601String(),
                'citations' => $data['citations'],
                'key_points' => collect($data['key_points'] ?? [])
                    ->filter(fn ($p) => is_string($p) && trim($p) !== '')
                    ->map(fn ($p) => mb_substr(trim($p), 0, 280))
                    ->take(4)
                    ->values()
                    ->all(),
            ]),
        ]);

        if (blank($article->slug)) {
            $article->slug = $this->uniqueSlug($data['title']);
        }

        $article->save();

        return $article;
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 1;

        while (Article::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    private function fail(EditorialJob $job, Story $story, string $message): EditorialJob
    {
        $job->update([
            'status' => EditorialJobStatus::Failed,
            'error' => $message,
            'completed_at' => now(),
        ]);
        $story->update(['status' => StoryStatus::Candidate]);

        return $job->refresh();
    }
}
