<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\View\View;

class TagController extends Controller
{
    private const PER_PAGE = 20;

    public function show(Tag $tag): View
    {
        $articles = $tag->articles()
            ->publiclyVisible()
            ->with(['category', 'author'])
            ->orderByDesc('published_at')
            ->orderByDesc('articles.id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // Same deliberate pagination SEO policy as /latest - see
        // LatestController and partials/seo.blade.php.
        $isFirstPage = $articles->currentPage() <= 1;

        return view('public.tag', [
            'tag' => $tag,
            'articles' => $articles,
            'seoTitle' => '#'.$tag->name.' — '.config('app.name', 'EPIC World'),
            'seoDescription' => "Articles tagged {$tag->name}.",
            'canonicalUrl' => $isFirstPage
                ? route('tag.show', $tag)
                : route('tag.show', [$tag, 'page' => $articles->currentPage()]),
            'indexable' => $isFirstPage,
            'showCanonical' => true,
            'robotsContent' => $isFirstPage ? 'index, follow' : 'noindex, follow',
        ]);
    }
}
