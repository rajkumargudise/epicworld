<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Models\WireItem;
use App\Services\NewsWire\NewsWireFetcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The public homepage, built entirely from real published content -
 * no mock data. Every section here degrades gracefully when the
 * underlying signal doesn't exist yet (no is_featured article, no
 * is_breaking article, a category with nothing published) rather
 * than fabricating placeholder rows.
 */
class HomeController extends Controller
{
    /**
     * How many categories to surface as homepage sections, and how
     * many articles inside each - both bounded so the homepage query
     * cost never grows with the size of the archive.
     */
    private const CATEGORY_SECTIONS = 6;

    private const ARTICLES_PER_SECTION = 4;

    private const LATEST_COUNT = 12;

    private const BREAKING_COUNT = 5;

    public function index(Request $request): View|RedirectResponse
    {
        // Old WordPress "/?p=<id>" links.
        $postId = $request->query('p');

        if (is_scalar($postId) && ctype_digit((string) $postId)) {
            $legacy = Article::publiclyVisible()
                ->whereJsonContains('editorial_metadata->wp_post_id', (int) $postId)
                ->first();

            if ($legacy) {
                return redirect()->route('article.show', $legacy, 301);
            }
        }

        $featured = Article::publiclyVisible()
            ->with(['category', 'author'])
            ->where('is_featured', true)
            ->orderByDesc('published_at')
            ->first()
            ?? Article::publiclyVisible()->with(['category', 'author'])->orderByDesc('published_at')->first();

        $latest = Article::publiclyVisible()
            ->with(['category', 'author'])
            ->when($featured, fn ($query) => $query->whereKeyNot($featured->id))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->take(self::LATEST_COUNT)
            ->get();

        // The one boolean the domain already has for "surface this
        // more prominently" - a stand-in trending/breaking strip that
        // simply doesn't render when no article has been flagged.
        $breaking = Article::publiclyVisible()
            ->with(['category'])
            ->where('is_breaking', true)
            ->orderByDesc('published_at')
            ->take(self::BREAKING_COUNT)
            ->get();

        $categorySections = Category::active()
            ->take(self::CATEGORY_SECTIONS)
            ->get()
            ->map(fn (Category $category) => [
                'category' => $category,
                'articles' => Article::publiclyVisible()
                    ->with(['author'])
                    ->where('category_id', $category->id)
                    ->orderByDesc('published_at')
                    ->take(self::ARTICLES_PER_SECTION)
                    ->get(),
            ])
            ->filter(fn (array $section) => $section['articles']->isNotEmpty())
            ->values();

        $wire = collect(array_keys(config('newswire.scopes')))->mapWithKeys(fn (string $scope) => [
            $scope => WireItem::articles()->where('scope', $scope)->newest()->take(8)->get(),
        ]);

        if (config('newswire.enabled') && app(NewsWireFetcher::class)->isStale()) {
            dispatch(fn () => app(NewsWireFetcher::class)->run())->afterResponse();
        }

        return view('public.home', [
            'wire' => $wire,
            'videos' => WireItem::videos()->newest()->take(4)->get(),
            'featured' => $featured,
            'latest' => $latest,
            'breaking' => $breaking,
            'categorySections' => $categorySections,
            'seoTitle' => config('app.name', 'EPIC World').' | Live World, India & Local News, Video & Explainers',
            'seoDescription' => 'Live world, India and local news with live TV and video, plus clear, well-sourced explainers on technology, business, science and more - updated every few minutes.',
            'canonicalUrl' => route('home'),
            'indexable' => true,
            'jsonLd' => $this->websiteJsonLd(),
        ]);
    }

    /**
     * Site-level structured data, kept on the homepage only rather
     * than repeated on every page. Uses nothing beyond what is
     * actually configured (the app name) and the site's own real
     * routes - never an invented logo, social profile, or contact
     * detail. The SearchAction describes the real /search feature
     * that already exists, not a promotional claim.
     */
    private function websiteJsonLd(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => route('home').'#organization',
                    'name' => config('app.name', 'EPIC World'),
                    'url' => route('home'),
                    'logo' => ['@type' => 'ImageObject', 'url' => url('/brand/icon-512.png'), 'width' => 512, 'height' => 512],
                ],
                [
                    '@type' => 'WebSite',
                    'publisher' => ['@id' => route('home').'#organization'],
                    'inLanguage' => 'en',
                    'name' => config('app.name', 'EPIC World'),
                    'url' => route('home'),
                    'potentialAction' => [
                        '@type' => 'SearchAction',
                        'target' => route('search').'?q={search_term_string}',
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
            ],
        ];
    }
}
