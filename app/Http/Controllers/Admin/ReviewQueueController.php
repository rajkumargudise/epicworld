<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ArticleStatus;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\Editorial\PublicationPolicy;
use App\Services\Editorial\PublishQueue;
use App\Services\Editorial\SensitiveContentRouter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One screen for the human-approval step of the automated pipeline:
 * AI-written drafts that reached Review are listed with what an editor
 * needs to decide, and "Approve & publish" runs the same
 * PublicationPolicy approve() then publish() the single-article
 * buttons use - every gate (quality re-check, sensitive-content
 * review, status order) still applies per article. Nothing here is
 * automatic: each article is published only because an authorized
 * editor chose it.
 */
class ReviewQueueController extends Controller
{
    public function __construct(
        private readonly PublicationPolicy $policy,
        private readonly SensitiveContentRouter $sensitivity,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Article::class);

        $articles = Article::query()
            ->with(['category', 'story.sources', 'author'])
            ->where('status', ArticleStatus::Review)
            ->orderByDesc('updated_at')
            ->paginate(25);

        $articles->getCollection()->each(function (Article $article) {
            $article->setAttribute('needs_sensitive_review', $this->sensitivity->requiresReview($article));
        });

        return view('admin.review', ['articles' => $articles]);
    }

    /**
     * Send a community post back to its author with feedback. The post
     * returns to Draft (editable by the author), never published.
     */
    public function reject(Request $request, Article $article): RedirectResponse
    {
        $this->authorize('approve', $article);

        abort_unless($article->isContributed() && $article->status === ArticleStatus::Review, 404);

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);

        $metadata = $article->editorial_metadata ?? [];
        $metadata['rejection_reason'] = trim($data['reason']);
        $metadata['rejected_at'] = now()->toIso8601String();

        $article->update(['status' => ArticleStatus::Draft, 'editorial_metadata' => $metadata]);

        return back()->with('status', 'Post sent back to the author with your feedback.');
    }

    /**
     * Approve the selected articles into the daily publishing queue: they are not published now,
     * the queue releases one per day at the daily slot (see PublishQueue).
     */
    public function queue(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
        ]);

        $queued = 0;
        $skipped = [];

        $articles = Article::query()->whereIn('id', $data['ids'])->where('status', ArticleStatus::Review)->orderBy('id')->get();

        foreach ($articles as $article) {
            $this->authorize('approve', $article);

            if ($this->policy->approve($article)) {
                $queued++;
            } else {
                $skipped[] = $article->title;
            }
        }

        $message = "Added {$queued} ".str('article')->plural($queued).' to the daily queue.';

        if ($queued > 0) {
            $last = app(PublishQueue::class)->projected()->last();
            $message .= $last ? ' The last one goes live on '.$last['at']->format('D j M, g:i A').' IST.' : '';
        }

        if ($skipped !== []) {
            $message .= ' Skipped (needs sensitive review or failed quality checks): '.implode('; ', array_slice($skipped, 0, 5)).'.';
        }

        return back()->with($queued > 0 ? 'status' : 'error', $message);
    }

    public function publish(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['integer'],
        ]);

        $published = 0;
        $skipped = [];

        $articles = Article::query()
            ->whereIn('id', $data['ids'])
            ->where('status', ArticleStatus::Review)
            ->get();

        foreach ($articles as $article) {
            $this->authorize('approve', $article);
            $this->authorize('publish', $article);

            if ($this->policy->approve($article) && $this->policy->publish($article->refresh())) {
                $published++;
            } else {
                $skipped[] = $article->title;
            }
        }

        $message = "Published {$published} ".str('article')->plural($published).'.';

        if ($skipped !== []) {
            $message .= ' Skipped (needs sensitive review or failed quality checks): '.implode('; ', array_slice($skipped, 0, 5)).'.';
        }

        return back()->with($published > 0 ? 'status' : 'error', $message);
    }
}
