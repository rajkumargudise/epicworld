<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'is_active',
        'is_sensitive',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_sensitive' => 'boolean',
        ];
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    /**
     * The public site's navigation and homepage sections only ever
     * offer categories an editor has actually enabled, in the order
     * they configured - never every row in the table.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
