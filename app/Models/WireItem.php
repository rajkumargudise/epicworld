<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WireItem extends Model
{
    protected $fillable = [
        'kind', 'scope', 'source', 'title', 'summary', 'url', 'url_hash',
        'image_url', 'video_id', 'published_at',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function scopeNewest(Builder $query): Builder
    {
        return $query->orderByDesc('published_at')->orderByDesc('id');
    }

    public function scopeArticles(Builder $query): Builder
    {
        return $query->where('kind', 'article');
    }

    public function scopeVideos(Builder $query): Builder
    {
        return $query->where('kind', 'video');
    }

    public function isVideo(): bool
    {
        return $this->kind === 'video' && $this->video_id !== null;
    }

    public function slug(): string
    {
        return Str::slug(Str::limit($this->title, 70, ''));
    }

    public function path(): string
    {
        return route('wire.show', ['item' => $this->id, 'slug' => $this->slug()]);
    }

    public function thumbnail(): ?string
    {
        if ($this->image_url) {
            return $this->image_url;
        }

        return $this->video_id ? "https://i.ytimg.com/vi/{$this->video_id}/hqdefault.jpg" : null;
    }
}
