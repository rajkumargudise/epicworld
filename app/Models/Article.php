<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use Illuminate\Database\Eloquent\Builder;
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
}
