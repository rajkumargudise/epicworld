<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Response;

/**
 * RSS 2.0 feed of the latest published stories - helps readers, aggregators
 * and search engines discover new content. Replaces the old WordPress /feed.
 */
class FeedController extends Controller
{
    public function __invoke(): Response
    {
        $articles = Article::publiclyVisible()
            ->where('allow_indexing', true)
            ->with(['category', 'author'])
            ->orderByDesc('published_at')
            ->limit(30)
            ->get();

        $site = e(config('app.name', 'EPIC World'));
        $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $out .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/"><channel>'."\n";
        $out .= "<title>{$site}</title>\n<link>".e(route('home'))."</link>\n";
        $out .= '<description>'.e('Live world, India and local news, video and explainers from '.config('app.name', 'EPIC World').'.')."</description>\n";
        $out .= "<language>en</language>\n";
        $out .= '<atom:link href="'.e(route('feed')).'" rel="self" type="application/rss+xml"/>'."\n";
        $out .= '<image><url>'.e(url('/brand/icon-512.png')).'</url><title>'.$site.'</title><link>'.e(route('home'))."</link></image>\n";

        if ($articles->isNotEmpty()) {
            $out .= '<lastBuildDate>'.$articles->first()->published_at->toRssString()."</lastBuildDate>\n";
        }

        foreach ($articles as $article) {
            $url = route('article.show', $article);
            $out .= "<item>\n";
            $out .= '<title>'.e($article->title)."</title>\n";
            $out .= '<link>'.e($url)."</link>\n";
            $out .= '<guid isPermaLink="true">'.e($url)."</guid>\n";
            $out .= '<pubDate>'.$article->published_at->toRssString()."</pubDate>\n";
            if ($article->category) {
                $out .= '<category>'.e($article->category->name)."</category>\n";
            }
            $out .= '<description>'.e((string) $article->displayExcerpt())."</description>\n";
            if ($article->featured_image && preg_match('#^https?://#i', $article->featured_image)) {
                $out .= '<media:content url="'.e($article->featured_image).'" medium="image"/>'."\n";
            }
            $out .= "</item>\n";
        }

        $out .= '</channel></rss>';

        return response($out, 200, ['Content-Type' => 'application/rss+xml; charset=utf-8']);
    }
}
