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

        $breadcrumbs = array_values(array_filter([
            ['name' => 'Home', 'url' => route('home')],
            $article->category ? ['name' => $article->category->name, 'url' => route('category.show', $article->category)] : null,
            ['name' => $article->title, 'url' => $canonicalUrl],
        ]));

        return view('public.article', [
            'article' => $article,
            'breadcrumbJsonLd' => [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => collect($breadcrumbs)->map(fn ($crumb, $i) => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $crumb['name'],
                    'item' => $crumb['url'],
                ])->all(),
            ],
            'comments' => $article->comments()->approved()->orderBy('approved_at')->get(),
            'commentToken' => \Illuminate\Support\Facades\Crypt::encryptString((string) now()->timestamp),
            'relatedArticles' => $article->relatedArticles(),
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
                'url' => route('home'),
                'logo' => ['@type' => 'ImageObject', 'url' => url('/brand/icon-512.png'), 'width' => 512, 'height' => 512],
            ],
            'inLanguage' => 'en',
            'isAccessibleForFree' => true,
            'wordCount' => str_word_count(strip_tags((string) $article->content)),
        ];

        $jsonLd['headline'] = mb_substr((string) $article->title, 0, 110);

        if ($article->displayExcerpt()) {
            $jsonLd['description'] = $article->displayExcerpt();
        }

        if ($article->published_at) {
            $jsonLd['datePublished'] = $article->published_at->toIso8601String();
        }

        if ($article->updated_content_at ?? $article->published_at) {
            $jsonLd['dateModified'] = ($article->updated_content_at ?? $article->published_at)->toIso8601String();
        }

        // Google's article rich results need an image; fall back to the brand card.
        $jsonLd['image'] = [$article->featured_image ?: url('/brand/og-default.jpg')];

        $jsonLd['author'] = $article->author
            ? ['@type' => 'Person', 'name' => $article->author->name]
            : ['@type' => 'Organization', 'name' => config('app.name', 'EPIC World'), 'url' => route('home')];

        if ($article->category) {
            $jsonLd['articleSection'] = $article->category->name;
        }

        // tags is already eager-loaded by show() above, so this reads
        // the loaded relation rather than issuing an extra query.
        $tagNames = $article->tags->pluck('name');
        if ($tagNames->isNotEmpty()) {
            $jsonLd['keywords'] = $tagNames->implode(', ');
        }

        return $jsonLd;
    }
}
