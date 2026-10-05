<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'story_id',
        'category_id',
        'author_id',
        'title',
        'slug',
        'dek',
        'content',
        'excerpt',
        'status',
        'content_type',
        'seo_title',
        'seo_description',
        'canonical_url',
        'featured_image',
        'reading_time_minutes',
        'published_at',
        'updated_content_at',
        'is_featured',
        'is_breaking',
        'allow_indexing',
        'editorial_metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => ArticleStatus::class,
            'published_at' => 'datetime',
            'updated_content_at' => 'datetime',
            'is_featured' => 'boolean',
            'is_breaking' => 'boolean',
            'allow_indexing' => 'boolean',
            'editorial_metadata' => 'array',
        ];
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function isContributed(): bool
    {
        return ($this->editorial_metadata['source'] ?? null) === 'contributor';
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    /**
     * The single, deliberately narrow definition of "publicly eligible"
     * every public route must share: Published, with a published_at in
     * the past. Nothing about editorial_metadata, sensitivity, or how
     * the content was generated affects this - once PublicationPolicy
     * has moved an Article to Published, it is public. This is the one
     * place that query is written, so every public controller filters
     * through it rather than re-deriving the same three conditions.
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('status', ArticleStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * reading_time_minutes is a column the editorial pipeline has never
     * populated (see Milestone 11's inspection notes) - this is a pure,
     * non-persisting presentation fallback, not a new domain concern:
     * roughly 200 words per minute, floored at one minute, computed
     * from the stored value when present so an editor can still set it
     * explicitly later without this helper overriding it.
     */
    public function displayReadingTimeMinutes(): int
    {
        if ($this->reading_time_minutes !== null) {
            return $this->reading_time_minutes;
        }

        $words = str_word_count(strip_tags((string) $this->content));

        return max(1, (int) ceil($words / 200));
    }

    /**
     * dek is the editorial standfirst; excerpt is a fallback summary.
     * Public templates need one "one-line description" value and
     * should not each re-implement this fallback order themselves.
     */
    public function displayExcerpt(): ?string
    {
        return $this->dek ?: $this->excerpt;
    }

    /**
     * The card structure the "Epic Story" reading template renders
     * (lead, numbered section cards, table of contents, takeaways).
     * Optional AI/editor-supplied key points live in
     * editorial_metadata['key_points'].
     *
     * @return array<string, mixed>
     */
    public function storyLayout(): array
    {
        $keyPoints = $this->editorial_metadata['key_points'] ?? null;

        return app(\App\Services\Content\StoryRenderer::class)
            ->render((string) $this->content, is_array($keyPoints) ? $keyPoints : null);
    }

    /**
     * content is authored as plain text - the admin editor is a plain
     * textarea (never a rich-text/HTML editor), and AiArticleGenerator
     * only ever produces a plain prose string (see AiArticleGenerator's
     * OUTPUT_SCHEMA, where 'body' is typed 'string' with no markup
     * instruction). No part of the editorial pipeline authors or
     * expects HTML here. This renders it as the public template's
     * .prose typography intends - paragraphs, one per blank line -
     * without ever letting stored text become live markup: every
     * character is escaped with e() *before* paragraph/line-break tags
     * are added around it, so a stored `<script>` (from a compromised
     * source, a citation, or an editor's own input) can never execute
     * in a visitor's browser. This is the one place that turns content
     * into HTML for display; every public and admin view should call
     * this rather than rendering $article->content directly.
     */
    public function displayContentHtml(): string
    {
        $paragraphs = preg_split('/\R{2,}/', trim((string) $this->content)) ?: [];

        return collect($paragraphs)
            ->map(fn (string $paragraph) => trim($paragraph))
            ->filter(fn (string $paragraph) => $paragraph !== '')
            ->map(fn (string $paragraph) => '<p>'.nl2br(e($paragraph), false).'</p>')
            ->implode("\n");
    }

    /**
     * A small, deterministic related-content mechanism - no AI
     * similarity, no embeddings, no popularity scoring that doesn't
     * exist. Fills up to $limit slots in a fixed preference order,
     * each tier a single bounded, publicly-visible, self-excluding
     * query, never repeating an article already chosen by an earlier
     * tier:
     *
     *   1. same category (this Article has no direct topic_id of its
     *      own - only its Story does - so category is the practical
     *      "same subject" signal at the Article level)
     *   2. shares at least one tag
     *   3. most recently published, as a deterministic fallback so a
     *      thin category/tag graph still fills the section
     *
     * Bounded to at most three queries (one per tier, skipped once
     * $limit slots are already filled) regardless of how large the
     * archive is - this does not scale per row, so it isn't an N+1
     * concern even though it isn't a single query.
     */
    public function relatedArticles(int $limit = 4): Collection
    {
        $related = new Collection;

        if ($this->category_id !== null) {
            $related = $related->merge(
                static::publiclyVisible()
                    ->with(['category', 'author'])
                    ->where('category_id', $this->category_id)
                    ->whereKeyNot($this->id)
                    ->orderByDesc('published_at')
                    ->orderByDesc('id')
                    ->take($limit)
                    ->get()
            );
        }

        if ($related->count() < $limit) {
            $tagIds = $this->relationLoaded('tags')
                ? $this->tags->pluck('id')
                : $this->tags()->pluck('tags.id');

            if ($tagIds->isNotEmpty()) {
                $remaining = $limit - $related->count();
                $excludeIds = $related->pluck('id')->push($this->id);

                $related = $related->merge(
                    static::publiclyVisible()
                        ->with(['category', 'author'])
                        ->whereNotIn('id', $excludeIds)
                        ->whereHas('tags', fn (Builder $query) => $query->whereIn('tags.id', $tagIds))
                        ->orderByDesc('published_at')
                        ->orderByDesc('id')
                        ->take($remaining)
                        ->get()
                );
            }
        }

        if ($related->count() < $limit) {
            $remaining = $limit - $related->count();
            $excludeIds = $related->pluck('id')->push($this->id);

            $related = $related->merge(
                static::publiclyVisible()
                    ->with(['category', 'author'])
                    ->whereNotIn('id', $excludeIds)
                    ->orderByDesc('published_at')
                    ->orderByDesc('id')
                    ->take($remaining)
                    ->get()
            );
        }

        return $related->take($limit)->values();
    }
}
