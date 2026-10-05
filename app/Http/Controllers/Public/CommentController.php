<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Comment;
use App\Support\SiteSettings;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/**
 * Public comment submission. A comment is NEVER visible on submission:
 * it is stored as "pending" and only a moderator's approval makes it
 * appear (every public read goes through Comment::approved()). Spam
 * defences: per-IP rate limits (see the "comment" limiter), a honeypot
 * field, a minimum fill-in time, a link cap and duplicate suppression.
 */
class CommentController extends Controller
{
    public function store(Request $request, Article $publicArticle, SiteSettings $site): RedirectResponse
    {
        abort_unless($site->commentsEnabled(), 404);

        $back = route('article.show', $publicArticle).'#comments';

        // Honeypot - silently pretend success so bots learn nothing.
        if (filled($request->input('website'))) {
            return redirect($back)->with('comment_status', 'Thanks! Your comment is awaiting moderation.');
        }

        if (! $this->filledInReasonableTime((string) $request->input('ct'))) {
            return redirect($back)->withInput()->with('comment_error', 'That was a bit quick - please read your comment and submit again.');
        }

        $data = $request->validate([
            'author_name' => ['required', 'string', 'min:2', 'max:80', 'regex:/^[^<>]+$/u'],
            'author_email' => ['required', 'string', 'email:rfc', 'max:150'],
            'body' => ['required', 'string', 'min:3', 'max:2000'],
        ], [
            'author_name.regex' => 'The name may not contain < or > characters.',
        ]);

        if (preg_match_all('#https?://|www\.#i', $data['body']) > 2) {
            return redirect($back)->withInput()->with('comment_error', 'Please include at most two links in a comment.');
        }

        $ipHash = hash('sha256', $request->ip().config('app.key'));
        $body = trim(preg_replace('/\R{3,}/', "\n\n", $data['body']) ?? $data['body']);

        $duplicate = Comment::query()
            ->where('article_id', $publicArticle->id)
            ->where('ip_hash', $ipHash)
            ->where('body', $body)
            ->exists();

        if (! $duplicate) {
            $isEditor = $request->user()?->isEditor() ?? false;

            Comment::create([
                'article_id' => $publicArticle->id,
                'user_id' => $request->user()?->id,
                'author_name' => trim($data['author_name']),
                'author_email' => strtolower($data['author_email']),
                'body' => $body,
                'status' => $isEditor ? Comment::APPROVED : Comment::PENDING,
                'approved_at' => $isEditor ? now() : null,
                'ip_hash' => $ipHash,
            ]);
        }

        return redirect($back)->with('comment_status', 'Thanks! Your comment is awaiting moderation and will appear once approved.');
    }

    private function filledInReasonableTime(string $token): bool
    {
        try {
            $issued = (int) Crypt::decryptString($token);
        } catch (DecryptException) {
            return false;
        }

        $elapsed = now()->timestamp - $issued;

        return $elapsed >= 3 && $elapsed <= 7200;
    }
}
