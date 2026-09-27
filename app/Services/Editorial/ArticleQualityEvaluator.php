<?php

namespace App\Services\Editorial;

use App\Models\Article;

/**
 * Checkable, deterministic gates an Article must clear before it is
 * ready for human review - not a judgment of writing quality, only of
 * the structural things that would make an Article unsafe or
 * unusable to review: missing required fields, no supporting facts,
 * or too little content to be a real draft. None of this is AI
 * judgment; every rule here is something a human reviewer could
 * verify by eye, which is the point - this stage exists so the
 * pipeline can tell "not ready" from "ready for a human" before any
 * AI-assisted evaluation is ever introduced.
 */
class ArticleQualityEvaluator
{
    public const MINIMUM_CONTENT_LENGTH = 40;

    /**
     * @return array{passed: bool, issues: array<int, string>}
     */
    public function evaluate(Article $article): array
    {
        $issues = [];

        if (trim((string) $article->title) === '') {
            $issues[] = 'missing_title';
        }

        if (trim((string) $article->content) === '') {
            $issues[] = 'missing_content';
        } elseif (mb_strlen(trim($article->content)) < self::MINIMUM_CONTENT_LENGTH) {
            $issues[] = 'content_too_short';
        }

        if ($article->category_id === null) {
            $issues[] = 'missing_category';
        }

        $story = $article->story;

        if ($story === null) {
            $issues[] = 'missing_story';
        } elseif (($story->facts ?? []) === []) {
            $issues[] = 'no_supporting_facts';
        }

        return [
            'passed' => $issues === [],
            'issues' => $issues,
        ];
    }
}
