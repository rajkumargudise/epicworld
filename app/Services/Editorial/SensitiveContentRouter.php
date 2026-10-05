<?php

namespace App\Services\Editorial;

use App\Models\Article;
use App\Models\Category;
use App\Models\Story;
use App\Models\Topic;

/**
 * Decides whether a Story's coverage requires mandatory human review
 * before it can be approved or published - politics, elections,
 * finance/investing, health/medical, legal, disasters/casualties,
 * allegations/accusations, or any other subject an editor has
 * flagged. The only signal this ever uses is an editor-configured
 * Topic::is_sensitive or Category::is_sensitive flag (see the
 * 2026_09_27_140000 migration) - never a Story's text, its AI-
 * generated content, or any inferred political, ideological, or
 * bias judgment. This router does not rank candidates, predict
 * elections, or score persuasion; it only asks "did an editor flag
 * this subject", which is a lookup, not a classification.
 *
 * A Story with no Topic is conservatively treated as sensitive: with
 * no editor-configured signal to say otherwise, there is nothing to
 * prove it's safe to fast-track, so it is routed to review rather
 * than assumed low-risk by default.
 */
class SensitiveContentRouter
{
    /**
     * @return array{sensitive: bool, reason: ?string, topic_id: ?int, topic_name: ?string, category_id: ?int, category_name: ?string, evaluated_at: string}
     */
    public function evaluate(Story $story): array
    {
        $topic = $story->topic;

        if ($topic === null) {
            return $this->decision(sensitive: true, reason: 'unclassified');
        }

        if ($topic->is_sensitive) {
            return $this->decision(true, 'topic', $topic, $topic->category);
        }

        $category = $topic->category;

        if ($category?->is_sensitive) {
            return $this->decision(true, 'category', $topic, $category);
        }

        return $this->decision(false, null, $topic, $category);
    }

    /**
     * Evaluate $article's Story and persist the decision onto
     * Article::editorial_metadata['sensitivity'] - the machine-
     * readable, explainable record of why (or why not) this article
     * requires human review. A prior human_reviewed_at confirmation
     * (see confirmHumanReview()) is always preserved across
     * re-evaluation: re-running this because the Story's topic
     * changed must never silently erase a review that already
     * happened.
     *
     * @return array{sensitive: bool, reason: ?string, topic_id: ?int, topic_name: ?string, category_id: ?int, category_name: ?string, evaluated_at: string, human_reviewed_at: ?string}
     */
    private function isHumanAuthored(Article $article): bool
    {
        $metadata = $article->editorial_metadata ?? [];

        return in_array($metadata['source'] ?? null, ['contributor', 'ai_blog'], true) || ($metadata['imported_from'] ?? null) === 'wordpress';
    }

    public function annotate(Article $article): array
    {
        $story = $article->story;
        if ($story !== null) {
            $decision = $this->evaluate($story);
        } elseif ($this->isHumanAuthored($article)) {
            // A contributor post or imported blog post has no AI story to
            // classify; the editor reviewing it is the human gate. It is
            // still held for explicit confirmation if its category is sensitive.
            $category = $article->category;
            $decision = $category?->is_sensitive
                ? $this->decision(sensitive: true, reason: 'sensitive_category', category: $category)
                : $this->decision(sensitive: false, reason: null, category: $category);
        } else {
            $decision = $this->decision(sensitive: true, reason: 'no_story');
        }

        $previouslyReviewed = $article->editorial_metadata['sensitivity']['human_reviewed_at'] ?? null;
        $decision['human_reviewed_at'] = $previouslyReviewed;

        $metadata = $article->editorial_metadata ?? [];
        $metadata['sensitivity'] = $decision;
        $article->update(['editorial_metadata' => $metadata]);

        return $decision;
    }

    /**
     * The explicit human action that clears a sensitive Article for
     * approval: nothing else in this service, or in
     * PublicationDecision/PublicationPolicy, ever sets
     * human_reviewed_at on its own.
     *
     * @return array{sensitive: bool, reason: ?string, topic_id: ?int, topic_name: ?string, category_id: ?int, category_name: ?string, evaluated_at: string, human_reviewed_at: ?string}
     */
    public function confirmHumanReview(Article $article): array
    {
        $decision = $this->annotate($article);
        $decision['human_reviewed_at'] = now()->toIso8601String();

        $metadata = $article->editorial_metadata ?? [];
        $metadata['sensitivity'] = $decision;
        $article->update(['editorial_metadata' => $metadata]);

        return $decision;
    }

    /**
     * True when $article is sensitive and has not been explicitly
     * human-reviewed yet - the single check PublicationPolicy relies
     * on to block approval and publication. Always re-evaluates (via
     * annotate()) rather than trusting stale metadata, so a Story
     * reclassified since the Article last passed through here is
     * caught immediately.
     */
    public function requiresReview(Article $article): bool
    {
        $decision = $this->annotate($article);

        return $decision['sensitive'] && $decision['human_reviewed_at'] === null;
    }

    private function decision(bool $sensitive, ?string $reason, ?Topic $topic = null, ?Category $category = null): array
    {
        return [
            'sensitive' => $sensitive,
            'reason' => $reason,
            'topic_id' => $topic?->id,
            'topic_name' => $topic?->name,
            'category_id' => $category?->id,
            'category_name' => $category?->name,
            'evaluated_at' => now()->toIso8601String(),
        ];
    }
}
