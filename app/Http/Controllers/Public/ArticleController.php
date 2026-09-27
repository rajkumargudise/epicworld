<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\View\View;

/**
 * The single article page. $publicArticle arrives already scoped to
 * publicly-visible content by the route's
 * Route::bind('publicArticle', ...) closure (routes/web.php, named
 * to avoid colliding with the admin CMS's own {article} binding) - a
 * slug belonging to a Draft, Review, or Scheduled article never
 * reaches this method at all, it 404s at the routing layer.
 */
class ArticleController extends Controller
{
    public function show(Article $publicArticle): View
    {
        $article = $publicArticle;

        $article->load([
            'category',
            'author',
            'tags',
            'story.sources',
        ]);

        $canonicalUrl = $article->canonical_url ?: route('article.show', $article);

        return view('public.article', [
            'article' => $article,
            'seoTitle' => $article->seo_title ?: $article->title,
            'seoDescription' => $article->seo_description ?: $article->displayExcerpt(),
            'canonicalUrl' => $canonicalUrl,
            'indexable' => $article->allow_indexing,
            'ogType' => 'article',
            'ogImage' => $article->featured_image,
            'jsonLd' => $article->allow_indexing ? $this->newsArticleJsonLd($article, $canonicalUrl) : null,
        ]);
    }

    /**
     * A minimal NewsArticle structured-data foundation - only the
     * fields this domain actually has values for, never a fabricated
     * publisher logo or a made-up author when the Article has none.
     *
     * @return array<string, mixed>
     */
    private function newsArticleJsonLd(Article $article, string $canonicalUrl): array
    {
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => $article->title,
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $canonicalUrl,
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => config('app.name', 'EPIC World'),
            ],
        ];

        if ($article->displayExcerpt()) {
            $jsonLd['description'] = $article->displayExcerpt();
        }

        if ($article->published_at) {
            $jsonLd['datePublished'] = $article->published_at->toIso8601String();
        }

        if ($article->updated_content_at ?? $article->published_at) {
            $jsonLd['dateModified'] = ($article->updated_content_at ?? $article->published_at)->toIso8601String();
        }

        if ($article->featured_image) {
            $jsonLd['image'] = [$article->featured_image];
        }

        if ($article->author) {
            $jsonLd['author'] = [
                '@type' => 'Person',
                'name' => $article->author->name,
            ];
        }

        return $jsonLd;
    }
}
