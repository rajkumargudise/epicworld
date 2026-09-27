<?php

namespace App\Services\Editorial;

use App\Enums\ArticleStatus;
use App\Enums\StoryStatus;
use App\Models\Article;

/**
 * Governs the two steps beyond PublicationDecision that only a human
 * editor may take: approving a reviewed Article for publication, and
 * actually publishing it. Nothing in this application calls these
 * methods automatically - they exist for the admin/CMS action a
 * later milestone will wire up, so the policy is correct and tested
 * before there is a button that triggers it.
 *
 * The pipeline is strict and one-directional: Draft -> Review
 * (PublicationDecision) -> Scheduled (approve) -> Published
 * (publish). Each method here only accepts an Article already in the
 * exact prior state; skipping a step, or repeating one, is refused
 * rather than silently coerced, so an editor (or a script driving
 * this service) always gets an honest true/false rather than a
 * status that quietly did something unexpected.
 */
class PublicationPolicy
{
    public function __construct(
        private readonly ArticleQualityEvaluator $evaluator,
    ) {}

    /**
     * Approve a Review article, moving it to Scheduled. Quality is
     * re-evaluated here rather than trusting the result
     * PublicationDecision recorded when the Article first reached
     * Review - its content may have been edited since. A failing
     * re-check records the new result but leaves the Article in
     * Review rather than pushing it backward to Draft: it still
     * needs a look, not a restart.
     *
     * Returns false, with no state change, for an Article that isn't
     * currently in Review - approval never skips ahead from Draft or
     * repeats itself on one already Scheduled or Published.
     */
    public function approve(Article $article): bool
    {
        if ($article->status !== ArticleStatus::Review) {
            return false;
        }

        $result = $this->evaluator->evaluate($article);
        $metadata = $article->editorial_metadata ?? [];
        $metadata['quality'] = [
            'passed' => $result['passed'],
            'issues' => $result['issues'],
            'evaluated_at' => now()->toIso8601String(),
        ];

        if (! $result['passed']) {
            $article->update(['editorial_metadata' => $metadata]);

            return false;
        }

        $metadata['approved_at'] = now()->toIso8601String();
        $article->update([
            'status' => ArticleStatus::Scheduled,
            'editorial_metadata' => $metadata,
        ]);

        return true;
    }

    /**
     * Publish an approved (Scheduled) Article: moves it to Published,
     * setting published_at, and moves its Story to Published too.
     * Idempotent - calling this again on an already-Published
     * Article is a no-op that returns true without touching
     * published_at, so a second call (a retry, a double click) can
     * never overwrite the real publish time.
     *
     * Returns false, with no state change, for anything not currently
     * Scheduled - publishing always follows an explicit approval.
     */
    public function publish(Article $article): bool
    {
        if ($article->status === ArticleStatus::Published) {
            return true;
        }

        if ($article->status !== ArticleStatus::Scheduled) {
            return false;
        }

        $article->update([
            'status' => ArticleStatus::Published,
            'published_at' => $article->published_at ?? now(),
        ]);

        $story = $article->story;

        if ($story !== null && $story->status !== StoryStatus::Published) {
            $story->update(['status' => StoryStatus::Published]);
        }

        return true;
    }
}
