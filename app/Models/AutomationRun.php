<?php

namespace App\Models;

use App\Enums\AutomationRunStatus;
use Illuminate\Database\Eloquent\Model;

class AutomationRun extends Model
{
    protected $fillable = [
        'run_type',
        'status',
        'started_at',
        'completed_at',
        'items_discovered',
        'items_processed',
        'items_published',
        'items_failed',
        'items_skipped',
        'error',
        'metrics',
    ];

    protected function casts(): array
    {
        return [
            'status' => AutomationRunStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'items_discovered' => 'integer',
            'items_processed' => 'integer',
            'items_published' => 'integer',
            'items_failed' => 'integer',
            'items_skipped' => 'integer',
            'metrics' => 'array',
        ];
    }
}
