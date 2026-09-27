<?php

namespace App\Services\Editorial;

use App\Enums\ArticleStatus;
use App\Models\Article;

/**
 * The one place that decides whether a drafted Article is ready to
 * move from Draft to Review. It never publishes anything itself -
 * "Review" means ready for a human editor to read, not ready to go
 * live. Actual publication stays a distinct, explicitly human action
 * (a later milestone's admin/CMS step), so an AI-assisted pipeline
 * can advance an Article only as far as a person's desk.
 */
class PublicationDecision
{
    public function __construct(
        private readonly ArticleQualityEvaluator $evaluator,
    ) {}

    /**
     * Evaluate $article and, if it passes quality gates, move it from
     * Draft to Review. Only ever acts on a Draft article - one
     * already in Review, Scheduled, Published or Archived is left
     * alone, since this decision has already been made (or overtaken
     * by an editor) for it. The evaluation result is always recorded
     * on editorial_metadata, whether or not it passed, so a reviewer
     * (or a retry) can see why.
     */
    public function decide(Article $article): Article
    {
        $result = $this->evaluator->evaluate($article);

        $metadata = $article->editorial_metadata ?? [];
        $metadata['quality'] = [
            'passed' => $result['passed'],
            'issues' => $result['issues'],
            'evaluated_at' => now()->toIso8601String(),
        ];

        $attributes = ['editorial_metadata' => $metadata];

        if ($result['passed'] && $article->status === ArticleStatus::Draft) {
            $attributes['status'] = ArticleStatus::Review;
        }

        $article->update($attributes);

        return $article;
    }
}
