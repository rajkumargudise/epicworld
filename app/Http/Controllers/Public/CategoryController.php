<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use Illuminate\View\View;

class CategoryController extends Controller
{
    private const PER_PAGE = 20;

    public function show(Category $category): View
    {
        $articles = Article::publiclyVisible()
            ->with(['category', 'author'])
            ->where('category_id', $category->id)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // Topics have no article-level relationship of their own (only
        // Story does), so there is no "topic page" to link to without
        // duplicating what Category already does. Surfacing them here
        // as search shortcuts reuses the existing search route instead
        // of inventing a parallel taxonomy/browsing system.
        $topics = $category->topics()->where('is_active', true)->orderBy('name')->get();

        return view('public.category', [
            'category' => $category,
            'articles' => $articles,
            'topics' => $topics,
            'seoTitle' => $category->name.' — '.config('app.name', 'EPIC World'),
            'seoDescription' => $category->description ?? "The latest {$category->name} coverage.",
            'canonicalUrl' => route('category.show', $category),
            'indexable' => true,
        ]);
    }
}
