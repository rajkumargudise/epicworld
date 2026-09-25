<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceFeed extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_id',
        'name',
        'feed_type',
        'url',
        'language',
        'region',
        'poll_interval_minutes',
        'last_fetched_at',
        'last_success_at',
        'last_failure_at',
        'last_error',
        'is_active',
        'configuration',
    ];

    protected function casts(): array
    {
        return [
            'last_fetched_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
            'is_active' => 'boolean',
            'configuration' => 'array',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}