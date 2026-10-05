<?php

namespace App\Services\Images;

use App\Models\Article;

/**
 * Attaches a free, credited featured image to an article that has none.
 * Best-effort and never throws: an article is never held back because an
 * image could not be found.
 */
class ArticleImageAttacher
{
    public function __construct(private readonly ImageFinder $finder) {}

    public function attach(Article $article, ?string $query = null): bool
    {
        if (filled($article->featured_image)) {
            return false;
        }

        // The AI's suggested words first, then the title, then the category.
        $hit = null;
        foreach (array_filter([$query, (string) $article->title, $article->category?->name]) as $candidate) {
            $hit = $this->finder->find((string) $candidate);

            if ($hit !== null) {
                break;
            }
        }

        if ($hit === null) {
            return false;
        }

        $metadata = $article->editorial_metadata ?? [];
        $metadata['image_credit'] = ['text' => $hit['credit'], 'url' => $hit['credit_url'], 'source' => $hit['source']];

        $article->update(['featured_image' => $hit['url'], 'editorial_metadata' => $metadata]);

        return true;
    }
}
