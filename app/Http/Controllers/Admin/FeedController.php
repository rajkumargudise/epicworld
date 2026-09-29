<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SourceFeed;
use App\Models\Story;
use Illuminate\View\View;

/**
 * A read-only operational view of feed health - the diagnostic
 * surface Milestone 15 requires so an administrator can see why
 * discovery did or didn't pick something up, without reading the
 * database directly. Gated on the same "may view editorial internals"
 * check as the Story queue (StoryPolicy::viewAny), since feed health
 * is editorial/operational information, not public content - nothing
 * here is ever exposed on the public site.
 */
class FeedController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Story::class);

        $feeds = SourceFeed::query()
            ->with('source')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('admin.feeds.index', ['feeds' => $feeds]);
    }
}
