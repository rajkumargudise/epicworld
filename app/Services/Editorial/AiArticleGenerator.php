<?php

namespace App\Services\Editorial;

use App\Enums\AiOperation;
use App\Enums\ArticleStatus;
use App\Enums\EditorialJobStatus;
use App\Enums\StoryStatus;
use App\Models\Article;
use App\Models\EditorialJob;
use App\Models\Story;
use App\Services\Ai\AiProviderManager;
use App\Services\Ai\AiRequest;
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
        );

        $result = $this->providers->resolve()->respond($request);

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
        return 'Draft a news article using only the supplied evidence. Do not include any name, '
            .'number, date, quote, or claim that the evidence does not support. For every factual '
            .'claim in the body, include the exact supporting text from the evidence in the '
            .'citations array - a claim with no matching citation will cause the entire draft to '
            .'be rejected. If the evidence is too thin for a complete article, write only as much '
            .'as it supports. Write original prose in your own words - never copy sentences from '
            .'the evidence - so a reader gets the full story without needing the source. '
            .'Format the body as plain text: an opening paragraph, then sections that each start '
            .'with a line like "## What happened", "## Background", "## Why it matters", "## What\'s next" '
            .'(use only sections the evidence supports), separated by blank lines. Use "- " lines for '
            .'lists and a "> " line only for a quote that appears verbatim in the evidence. Also return '
            .'key_points: 3 or 4 short, standalone takeaway sentences.';
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
            if (! is_string($citation) || ! $factSheet->supports($citation)) {
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
