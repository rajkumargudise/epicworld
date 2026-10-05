<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reader comment. Nothing is public until a moderator approves it:
 * every public query goes through scopeApproved(), and new comments
 * are always created as "pending".
 */
class Comment extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const SPAM = 'spam';

    protected $fillable = ['article_id', 'user_id', 'author_name', 'author_email', 'body', 'status', 'ip_hash', 'approved_at'];

    protected $hidden = ['author_email', 'ip_hash'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::APPROVED);
    }
}
