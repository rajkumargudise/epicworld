<?php

namespace App\Services\Editorial;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Story;
use Illuminate\Support\Str;

/**
 * Produces the first Article draft for a Story - deterministically,
 * from the Story's fact sheet (see FactExtractor) only. No AI
 * provider is involved yet: this stage exists so the editorial
 * pipeline has a complete, testable path from Story to a real Article
 * row before any AI-assisted generation is introduced in a later
 * milestone. The draft it produces is intentionally plain - a
 * source-attributed compilation of known facts, not prose - and is
 * always still Draft status, requiring an editor (or, later, an
 * AI-assisted rewrite subject to the same fact sheet) before
 * anything approaches publication.
 */
class ArticleDrafter
{
    public const GENERATION_METHOD = 'deterministic_template';

    /**
     * Draft an Article for $story, or return its existing one
     * unchanged. Never redrafts over an Article that already exists -
     * once a draft exists, editorial or AI work on it belongs to a
     * distinct revision step, not this one.
     *
     * Returns null when the Story has no extracted facts yet: a
     * draft has to be built from something a source actually said,
     * never fabricated to fill the gap.
     */
    public function draftFor(Story $story): ?Article
    {
        $existing = $story->article;

        if ($existing !== null) {
            return $existing;
        }

        $facts = $story->facts ?? [];

        if ($facts === []) {
            return null;
        }

        return Article::create([
            'story_id' => $story->id,
            'category_id' => $story->topic?->category_id,
            'title' => $story->title,
            'slug' => $this->uniqueSlug($story),
            'dek' => $story->summary,
            'content' => $this->compileContent($facts),
            'status' => ArticleStatus::Draft,
            'editorial_metadata' => [
                'generation_method' => self::GENERATION_METHOD,
                'generated_at' => now()->toIso8601String(),
                'source_count' => count($facts),
            ],
        ]);
    }

    /**
     * A plain, attributed compilation of every fact on record - never
     * prose, never a claim no source made. Each source's reported
     * fields appear under its own name so a reader (or reviewer) can
     * see exactly which source said what.
     */
    private function compileContent(array $facts): string
    {
        $sections = array_map(function (array $fact): string {
            $lines = ["According to {$fact['source_name']}:"];

            foreach ($fact['reported'] as $field => $value) {
                if ($field === 'source_url') {
                    continue;
                }

                $lines[] = '- '.ucfirst(str_replace('_', ' ', $field)).': '.$value;
            }

            if (isset($fact['reported']['source_url'])) {
                $lines[] = 'Source: '.$fact['reported']['source_url'];
            }

            return implode("\n", $lines);
        }, $facts);

        return implode("\n\n", $sections);
    }

    private function uniqueSlug(Story $story): string
    {
        $base = Str::slug($story->title);
        $slug = $base;
        $suffix = 1;

        while (Article::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}
