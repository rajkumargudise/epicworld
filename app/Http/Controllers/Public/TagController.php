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

        return view('public.tag', [
            'tag' => $tag,
            'articles' => $articles,
            'seoTitle' => '#'.$tag->name.' — '.config('app.name', 'EPIC World'),
            'seoDescription' => "Articles tagged {$tag->name}.",
            'canonicalUrl' => route('tag.show', $tag),
            'indexable' => true,
        ]);
    }
}
