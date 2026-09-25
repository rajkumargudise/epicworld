<?php

namespace App\Models;

use App\Enums\ArticleStatus;
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
}
