<?php

namespace App\Services\Editorial;

use App\Enums\AiOperation;
use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use App\Services\Ai\AiProviderManager;
use App\Services\Ai\AiRequest;
use App\Services\Ai\Exceptions\AiProviderUnavailableException;
use App\Services\Images\ArticleImageAttacher;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Writes a complete, publication-style blog post (introduction,
 * sections, takeaways, conclusion) on a topic an editor chooses, with a
 * free credited image. The AI is told to keep specific facts to the
 * supplied notes and to add only widely known background; the result is
 * saved as a Draft, passed through the same quality and sensitivity
 * gates as everything else, and lands in the review queue. A person
 * still approves and publishes it.
 */
class AiBlogGenerator
{
    public const OUTPUT_SCHEMA = [
        'title' => ['type' => 'string', 'max_length' => 180],
        'dek' => ['type' => 'string', 'max_length' => 300, 'required' => false],
        'body' => ['type' => 'string'],
        'key_points' => ['type' => 'array', 'required' => false],
        'tags' => ['type' => 'array', 'required' => false],
        'image_query' => ['type' => 'string', 'max_length' => 80, 'required' => false],
    ];

    private const LENGTHS = [
        'medium' => '700 to 1,000 words',
        'long' => '1,000 to 1,500 words',
        'deep' => '1,500 to 2,200 words',
    ];

    public function __construct(
        private readonly AiProviderManager $providers,
        private readonly PublicationDecision $decision,
        private readonly ArticleImageAttacher $images,
    ) {}

    /**
     * @param  array<int, string>  $notes  facts/notes the post should be grounded in
     * @param  array<int, array{name: string, url: string}>  $sourceLinks  credited sources
     * @param  array<string, mixed>  $extraMetadata
     */
    public function generate(
        string $topic,
        ?Category $category = null,
        array $notes = [],
        string $length = 'long',
        array $sourceLinks = [],
        array $extraMetadata = [],
    ): Article {
        $length = self::LENGTHS[$length] ?? self::LENGTHS['long'];

        $request = new AiRequest(
            operation: AiOperation::ArticleGeneration,
            storyId: 0,
            facts: array_values(array_filter(array_map('trim', $notes))),
            instructions: $this->instructions($topic, $category, $length),
            schema: self::OUTPUT_SCHEMA,
            allowBackground: true,
        );

        $result = $this->providers->resolve()->respond($request);

        if (in_array($result->status->value, ['rate_limited', 'authentication_error', 'timeout'], true)) {
            throw new AiProviderUnavailableException((string) $result->error);
        }

        if (! $result->successful()) {
            throw new RuntimeException(sprintf('%s (%s): %s', $result->provider, $result->status->value, $result->error));
        }

        $data = $result->data;
        $body = trim((string) $data['body']);

        $metadata = array_merge([
            'source' => 'ai_blog',
            'generation_method' => 'ai_assisted',
            'generated_at' => now()->toIso8601String(),
            'topic' => $topic,
            'key_points' => collect($data['key_points'] ?? [])->filter(fn ($p) => is_string($p) && trim($p) !== '')->map(fn ($p) => mb_substr(trim($p), 0, 280))->take(4)->values()->all(),
            'source_links' => array_values($sourceLinks),
            'provider' => $result->provider,
            'model' => $result->model,
        ], $extraMetadata);

        $article = Article::create([
            'category_id' => $category?->id,
            'title' => Str::limit(trim((string) $data['title']), 180, ''),
            'slug' => $this->uniqueSlug((string) $data['title']),
            'dek' => isset($data['dek']) ? Str::limit(trim((string) $data['dek']), 300, '') : null,
            'content' => $body,
            'status' => ArticleStatus::Draft,
            'reading_time_minutes' => max(1, (int) ceil(str_word_count($body) / 200)),
            'allow_indexing' => true,
            'editorial_metadata' => $metadata,
        ]);

        $this->attachTags($article, (array) ($data['tags'] ?? []));
        $this->images->attach($article, $data['image_query'] ?? null);

        return $this->decision->decide($article->refresh());
    }

    private function instructions(string $topic, ?Category $category, string $length): string
    {
        return "Write a complete, original, publication-quality blog post for a news and explainers website on this topic: \"{$topic}\"."
            .($category ? " It will be published in the \"{$category->name}\" category." : '')
            ." Target length: {$length}. Write in clear, engaging, professional prose for a general reader, in your own words. "
            .'Structure the body as plain text: a strong opening paragraph (no heading) that hooks the reader and states what the post covers; '
            .'then 5 to 8 sections, each beginning with a line like "## What happened" or "## Why it matters" '
            .'(use sections such as Background, Key details, Why it matters, What experts and observers are watching, What happens next, and a short FAQ-style section where useful); '
            .'use "- " bullet lines for lists and a final "## Bottom line" section. Separate paragraphs with a blank line. '
            .'Be accurate: ground every event-specific claim in the supplied source material, and add only widely known background, definitions and context. '
            .'Never invent statistics, quotes, dates, names or events; if a number is not in the source material, describe it generally. '
            .'Do not mention that you are an AI or refer to "the source material" in the text. '
            .'Also return: title (clear, under 65 characters, no clickbait), dek (a 140-160 character summary sentence), '
            .'key_points (3 or 4 short standalone takeaway sentences), tags (3 to 5 short topic tags), and '
            .'image_query (2 to 4 plain words describing a generic photograph that would illustrate the post, e.g. "classroom students"; no people\'s names).';
    }

    /**
     * @param  array<int, mixed>  $tags
     */
    private function attachTags(Article $article, array $tags): void
    {
        $ids = collect($tags)
            ->filter(fn ($t) => is_string($t) && trim($t) !== '')
            ->map(fn ($t) => Str::limit(trim(strip_tags($t)), 40, ''))
            ->unique(fn ($t) => Str::slug($t))
            ->take(5)
            ->map(fn ($name) => Tag::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id)
            ->values()
            ->all();

        if ($ids !== []) {
            $article->tags()->sync($ids);
        }
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title), 80, '') ?: 'post';
        $slug = $base;

        while (Article::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}
