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

        return view('public.category', [
            'category' => $category,
            'articles' => $articles,
            'seoTitle' => $category->name.' — '.config('app.name', 'EPIC World'),
            'seoDescription' => $category->description ?? "The latest {$category->name} coverage.",
            'canonicalUrl' => route('category.show', $category),
            'indexable' => true,
        ]);
    }
}
