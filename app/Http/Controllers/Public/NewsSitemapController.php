<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Response;

/**
 * A Google News-format sitemap: indexable articles published in the last
 * two days (Google's window), newest first, capped at 1,000 entries.
 */
class NewsSitemapController extends Controller
{
    public function __invoke(): Response
    {
        $publication = e(config('app.name', 'EPIC World'));

        $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">'."\n";

        Article::publiclyVisible()
            ->where('allow_indexing', true)
            ->where('published_at', '>=', now()->subDays(2))
            ->select(['id', 'slug', 'title', 'published_at'])
            ->orderByDesc('published_at')
            ->limit(1000)
            ->get()
            ->each(function (Article $article) use (&$out, $publication) {
                $out .= "  <url>\n";
                $out .= '    <loc>'.e(route('article.show', $article))."</loc>\n";
                $out .= "    <news:news>\n";
                $out .= "      <news:publication><news:name>{$publication}</news:name><news:language>en</news:language></news:publication>\n";
                $out .= '      <news:publication_date>'.e($article->published_at->toAtomString())."</news:publication_date>\n";
                $out .= '      <news:title>'.e($article->title)."</news:title>\n";
                $out .= "    </news:news>\n";
                $out .= "  </url>\n";
            });

        $out .= '</urlset>';

        return response($out, 200, ['Content-Type' => 'application/xml']);
    }
}
