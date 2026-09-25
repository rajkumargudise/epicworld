<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Source extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'description',
        'source_type',
        'homepage_url',
        'logo_url',
        'is_trusted',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_trusted' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function feeds(): HasMany
    {
        return $this->hasMany(SourceFeed::class);
    }

    public function stories(): BelongsToMany
    {
        return $this->belongsToMany(Story::class)
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
}