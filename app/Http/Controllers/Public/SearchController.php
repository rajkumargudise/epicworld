<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A lightweight, database-native search over publicly visible
 * articles - LIKE-based matching that works unchanged on SQLite
 * (tests) and on MariaDB/MySQL shared hosting (production), and
 * deliberately not a FULLTEXT index: InnoDB fulltext support and
 * configuration is inconsistent enough across shared hosts that
 * relying on it would violate "avoid database features unavailable
 * or unreliable on typical shared hosting". No external search
 * infrastructure, no embeddings.
 */
class SearchController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));

        // An empty or whitespace-only query gets a validation/empty
        // state, never an unscoped "all published articles" listing -
        // that would just be a slower, worse /latest.
        if ($query === '') {
            return view('public.search', [
                'query' => $query,
                'articles' => null,
                'seoTitle' => 'Search — '.config('app.name', 'EPIC World'),
                'seoDescription' => 'Search EPIC World articles.',
                'canonicalUrl' => route('search'),
                'indexable' => false,
            ]);
        }

        $like = self::likePattern($query);

        // '!' rather than the more conventional backslash: MySQL
        // interprets backslash escapes inside a quoted string literal
        // by default, but SQLite does not, so the same ESCAPE '\\'
        // clause text would mean two different things on the two
        // databases this application actually runs on (SQLite in
        // tests, MariaDB/MySQL in production). '!' has no special
        // meaning to either engine's string-literal parser, so the
        // same query text and the same escaped pattern (likePattern())
        // behave identically on both.
        $articles = Article::publiclyVisible()
            ->with(['category', 'author'])
            ->where(function (Builder $where) use ($like) {
                $where->whereRaw("LOWER(title) LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("LOWER(dek) LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("LOWER(excerpt) LIKE ? ESCAPE '!'", [$like])
                    ->orWhereHas('tags', function (Builder $tags) use ($like) {
                        $tags->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$like]);
                    })
                    ->orWhereHas('category', function (Builder $categories) use ($like) {
                        $categories->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$like]);
                    })
                    ->orWhereHas('story.topic', function (Builder $topics) use ($like) {
                        $topics->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$like]);
                    });
            })
            // published_at desc, id desc: the same deterministic
            // tiebreaker every other public listing uses, so a search
            // paginates the same stable way as /latest does.
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('public.search', [
            'query' => $query,
            'articles' => $articles,
            'seoTitle' => "Search: {$query} — ".config('app.name', 'EPIC World'),
            'seoDescription' => "Search results for \"{$query}\" on ".config('app.name', 'EPIC World').'.',
            'canonicalUrl' => route('search'),
            // Search-result pages are never indexable: a query string
            // can produce effectively unlimited, thin, duplicate-prone
            // URLs, which is exactly what requirement #8 warns against.
            'indexable' => false,
        ]);
    }

    /**
     * A case-insensitive, wildcard-safe LIKE pattern: literal '%', '_'
     * and '!' in the user's own search text are escaped with '!' so a
     * search for "50%_off" or "wait!" matches that text literally
     * rather than the user's characters being (mis)read as SQL
     * wildcards or the escape character itself.
     */
    private static function likePattern(string $term): string
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term));

        return '%'.$escaped.'%';
    }
}
