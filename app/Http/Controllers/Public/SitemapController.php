<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Http\Response;

/**
 * A single, hand-written sitemap covering exactly the content the
 * public site itself already considers indexable - reusing Article's
 * own publiclyVisible() scope and allow_indexing flag, and Category's
 * active() scope, rather than re-deriving those rules here.
 *
 * Every URL section is built by cursor()-iterating the database one
 * row at a time (never Article::all() or ->get() on the whole table),
 * so building this response never holds more than a handful of model
 * instances in memory regardless of how large the archive grows - the
 * requirement this milestone calls out explicitly for shared hosting.
 * URL sections are separated into their own methods so that a future
 * sitemap index/pagination split (if a single sitemap ever gets too
 * large to serve as one response) can be added around these without
 * rewriting the public model scope - deliberately not building that
 * framework now, since nothing here needs it yet.
 */
class SitemapController extends Controller
{
    public function index(): Response
    {
        ob_start();

        echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        $this->writeDiscoveryUrls();
        $this->writeStaticUrls();
        $this->writeCategoryUrls();
        $this->writeTagUrls();
        $this->writeArticleUrls();

        echo '</urlset>';

        return response(ob_get_clean(), 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * The homepage and the /latest feed are only genuinely indexable
     * discovery pages once there is at least one indexable published
     * article to discover through them - never listed unconditionally.
     */
    private function writeDiscoveryUrls(): void
    {
        if (! Article::publiclyVisible()->where('allow_indexing', true)->exists()) {
            return;
        }

        $this->writeUrl(route('home'));
        $this->writeUrl(route('latest'));
    }

    /**
     * Milestone 18: static, always-indexable pages that exist
     * independently of editorial content - currently just /privacy.
     */
    private function writeStaticUrls(): void
    {
        $this->writeUrl(route('privacy'));
    }

    /**
     * Only active categories that currently have at least one
     * indexable, publicly visible article - an active-but-empty
     * category page has nothing to offer a crawler.
     */
    private function writeCategoryUrls(): void
    {
        Category::active()
            ->whereHas('articles', fn ($query) => $query->publiclyVisible()->where('allow_indexing', true))
            ->select(['id', 'slug', 'sort_order'])
            ->cursor()
            ->each(fn (Category $category) => $this->writeUrl(route('category.show', $category)));
    }

    /**
     * Same principle as categories: only tags that currently tag at
     * least one indexable, publicly visible article.
     */
    private function writeTagUrls(): void
    {
        Tag::query()
            ->whereHas('articles', fn ($query) => $query->publiclyVisible()->where('allow_indexing', true))
            ->select(['id', 'slug'])
            ->cursor()
            ->each(fn (Tag $tag) => $this->writeUrl(route('tag.show', $tag)));
    }

    /**
     * Every publicly visible, indexable article - draft/review/
     * scheduled/archived/future-dated and allow_indexing=false
     * articles are excluded by publiclyVisible() and the explicit
     * allow_indexing filter respectively, the same two rules the
     * article page itself uses to decide indexability.
     */
    private function writeArticleUrls(): void
    {
        Article::publiclyVisible()
            ->where('allow_indexing', true)
            ->select(['id', 'slug', 'published_at', 'updated_content_at'])
            ->orderBy('id')
            ->cursor()
            ->each(function (Article $article): void {
                $lastmod = ($article->updated_content_at ?? $article->published_at)?->toAtomString();
                $this->writeUrl(route('article.show', $article), $lastmod);
            });
    }

    private function writeUrl(string $loc, ?string $lastmod = null): void
    {
        echo '  <url>'."\n";
        echo '    <loc>'.e($loc).'</loc>'."\n";
        if ($lastmod !== null) {
            echo '    <lastmod>'.e($lastmod).'</lastmod>'."\n";
        }
        echo '  </url>'."\n";
    }
}
