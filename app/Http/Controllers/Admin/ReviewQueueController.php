<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ArticleStatus;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\Editorial\PublicationPolicy;
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
            ->with(['category', 'story.sources'])
            ->where('status', ArticleStatus::Review)
            ->orderByDesc('updated_at')
            ->paginate(25);

        $articles->getCollection()->each(function (Article $article) {
            $article->setAttribute('needs_sensitive_review', $this->sensitivity->requiresReview($article));
        });

        return view('admin.review', ['articles' => $articles]);
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
