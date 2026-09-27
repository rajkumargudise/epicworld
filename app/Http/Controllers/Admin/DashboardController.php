<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ArticleStatus;
use App\Enums\StoryStatus;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Story;
use Illuminate\View\View;

/**
 * A minimal at-a-glance summary of the editorial queue - counts only,
 * no charts, no realtime updates. Every number is a live query
 * against the real domain tables, never mock data. Gated on the same
 * "may view the editorial queue" check as the Story list, since the
 * dashboard is itself just a summary of that queue.
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Story::class);

        return view('admin.dashboard', [
            'candidateStories' => Story::query()->where('status', StoryStatus::Candidate)->count(),
            'processingStories' => Story::query()->where('status', StoryStatus::Processing)->count(),
            'reviewStories' => Story::query()->where('status', StoryStatus::Review)->count(),
            'draftArticles' => Article::query()->where('status', ArticleStatus::Draft)->count(),
            'reviewArticles' => Article::query()->where('status', ArticleStatus::Review)->count(),
            'scheduledArticles' => Article::query()->where('status', ArticleStatus::Scheduled)->count(),
            'publishedArticles' => Article::query()->where('status', ArticleStatus::Published)->count(),
            'sensitivePendingReview' => Article::query()
                ->whereIn('status', [ArticleStatus::Review, ArticleStatus::Scheduled])
                ->get()
                ->filter(fn (Article $article) => (bool) ($article->editorial_metadata['sensitivity']['sensitive'] ?? false)
                    && ($article->editorial_metadata['sensitivity']['human_reviewed_at'] ?? null) === null)
                ->count(),
        ]);
    }
}
