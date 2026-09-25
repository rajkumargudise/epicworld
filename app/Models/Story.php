<?php

namespace App\Models;

use App\Enums\StoryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Story extends Model
{
    use HasFactory;

    protected $fillable = [
        'topic_id',
        'title',
        'slug',
        'summary',
        'canonical_url',
        'content_hash',
        'status',
        'importance',
        'occurred_at',
        'first_seen_at',
        'last_seen_at',
        'facts',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => StoryStatus::class,
            'occurred_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'facts' => 'array',
            'metadata' => 'array',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(Source::class)
            ->withPivot([
                'source_url',
                'external_id',
                'title',
                'summary',
                'published_at',
                'discovered_at',
                'metadata',
            ])
            ->withTimestamps();
    }

    public function article(): HasOne
    {
        return $this->hasOne(Article::class);
    }
}
