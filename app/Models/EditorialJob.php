<?php

namespace App\Models;

use App\Enums\EditorialJobStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EditorialJob extends Model
{
    protected $fillable = [
        'story_id',
        'article_id',
        'job_type',
        'status',
        'provider',
        'model',
        'attempts',
        'error',
        'input',
        'output',
        'metadata',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EditorialJobStatus::class,
            'attempts' => 'integer',
            'input' => 'array',
            'output' => 'array',
            'metadata' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
