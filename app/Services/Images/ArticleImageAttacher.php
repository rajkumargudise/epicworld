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

        $hit = $this->finder->find($query ?: (string) $article->title);

        if ($hit === null) {
            return false;
        }

        $metadata = $article->editorial_metadata ?? [];
        $metadata['image_credit'] = ['text' => $hit['credit'], 'url' => $hit['credit_url'], 'source' => $hit['source']];

        $article->update(['featured_image' => $hit['url'], 'editorial_metadata' => $metadata]);

        return true;
    }
}
