<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Services\Editorial\SensitiveContentRouter;
use Illuminate\View\View;

/**
 * Read-only views onto the Story queue - the CMS never writes to a
 * Story directly; everything actionable (drafting, review,
 * publication) happens through its Article.
 */
class StoryController extends Controller
{
    public function __construct(
        private readonly SensitiveContentRouter $sensitivityRouter,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Story::class);

        $stories = Story::query()
            ->with(['topic.category', 'article'])
            ->withCount('sources')
            ->latest('first_seen_at')
            ->paginate(25);

        // The router's evaluate() is a pure read against the Story's
        // Topic/Category - no metadata is written here, unlike
        // annotate()/requiresReview() which persist onto an Article.
        $sensitivity = $stories->getCollection()
            ->mapWithKeys(fn (Story $story) => [$story->id => $this->sensitivityRouter->evaluate($story)]);

        return view('admin.stories.index', ['stories' => $stories, 'sensitivity' => $sensitivity]);
    }

    public function show(Story $story): View
    {
        $this->authorize('view', $story);

        $story->load(['topic.category', 'sources', 'article']);

        return view('admin.stories.show', [
            'story' => $story,
            'factSheet' => $story->factSheet(),
            'sensitivity' => $this->sensitivityRouter->evaluate($story),
        ]);
    }
}
