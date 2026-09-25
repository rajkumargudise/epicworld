<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id',
        'type',
        'path',
        'url',
        'alt_text',
        'caption',
        'credit',
        'width',
        'height',
        'size_bytes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}