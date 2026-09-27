<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\View\View;

/**
 * The full, deterministically ordered chronological feed. published_at
 * desc is the primary key, but two articles can share a published_at
 * (a batch publish, or second-precision collisions), so id desc is an
 * explicit tiebreaker - "deterministic ordering" means paginating this
 * twice must return the same pages, which published_at alone does not
 * guarantee.
 */
class LatestController extends Controller
{
    private const PER_PAGE = 20;

    public function index(): View
    {
        $articles = Article::publiclyVisible()
            ->with(['category', 'author'])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // Pagination SEO policy: page one is the real, indexable
        // destination and canonicalizes to the clean /latest URL.
        // Page two and beyond are deliberately noindex (they're
        // near-duplicate listings, not distinct content worth
        // ranking) but still "follow" so crawlers keep discovering
        // articles through them, and each carries a self-referencing
        // canonical rather than none at all - see partials/seo.blade.php.
        $isFirstPage = $articles->currentPage() <= 1;

        return view('public.latest', [
            'articles' => $articles,
            'seoTitle' => 'Latest — '.config('app.name', 'EPIC World'),
            'seoDescription' => 'The latest published articles, in chronological order.',
            'canonicalUrl' => $isFirstPage ? route('latest') : route('latest', ['page' => $articles->currentPage()]),
            'indexable' => $isFirstPage,
            'showCanonical' => true,
            'robotsContent' => $isFirstPage ? 'index, follow' : 'noindex, follow',
        ]);
    }
}
