<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ArticleStatus;
use App\Enums\StoryStatus;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Comment;
use App\Models\ContactMessage;
use App\Models\EditorialJob;
use App\Models\SourceFeed;
use App\Models\User;
use App\Models\WireItem;
use App\Services\Ai\AiProviderManager;
use App\Services\NewsWire\NewsWireFetcher;
use App\Support\SiteSettings;
use Illuminate\Support\Facades\Cache;
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
    public function index(SiteSettings $site, AiProviderManager $ai): View
    {
        $this->authorize('viewAny', Story::class);

        $checks = collect(LaunchController::checks($site, $ai))->flatten(1);
        $lastWire = Cache::get(NewsWireFetcher::LAST_RUN_KEY);

        $upcoming = Article::query()->where('status', ArticleStatus::Published)->where('published_at', '>', now())
            ->orderBy('published_at')->limit(10)->get(['id', 'title', 'slug', 'published_at']);
        $recent = Article::query()->with('category:id,name')->latest('updated_at')->limit(8)->get(['id', 'title', 'slug', 'status', 'category_id', 'published_at', 'updated_at']);

        return view('admin.dashboard', [
            'upcoming' => $upcoming,
            'recent' => $recent,
            'liveCount' => Article::query()->publiclyVisible()->count(),
            'published7' => Article::query()->publiclyVisible()->where('published_at', '>=', now()->subDays(7))->count(),
            'commentsTotal' => Comment::query()->count(),
            'deskHot' => $lastWire !== null,
            'pendingComments' => Comment::query()->where('status', Comment::PENDING)->count(),
            'contributorReview' => Article::query()->where('status', ArticleStatus::Review)->get()->filter(fn (Article $a) => $a->isContributed())->count(),
            'unreadMessages' => ContactMessage::query()->whereNull('read_at')->count(),
            'wireItems' => WireItem::query()->count(),
            'wireLastRun' => $lastWire ? \Carbon\Carbon::createFromTimestamp($lastWire) : null,
            'failingFeeds' => SourceFeed::query()->whereNotNull('last_failure_at')->whereColumn('last_failure_at', '>', 'last_success_at')->count(),
            'pendingJobs' => EditorialJob::query()->where('status', 'pending')->count(),
            'aiReady' => $ai->isReady(),
            'userCount' => User::query()->count(),
            'contributorCount' => User::query()->where('role', User::ROLE_CONTRIBUTOR)->count(),
            'readiness' => ['pass' => $checks->where('status', 'pass')->count(), 'total' => $checks->count()],
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
