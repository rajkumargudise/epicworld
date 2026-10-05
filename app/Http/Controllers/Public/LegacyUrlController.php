<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps old WordPress links working after cutover. The importer kept
 * each post's WordPress slug, so any unmatched URL whose last path
 * segment is a publicly visible article's slug (/slug/,
 * /2026/04/slug/, ...) - or an old ?p=<id> link - is permanently
 * redirected to the article's new canonical URL. Anything else is a
 * normal 404.
 */
class LegacyUrlController extends Controller
{
    public function __invoke(Request $request): Response
    {
        if ($request->isMethod('GET') && ! $request->is('admin/*', 'article/*', 'api/*')) {
            $article = $this->match($request);

            if ($article) {
                return redirect()->route('article.show', $article, 301);
            }
        }

        abort(404);
    }

    private function match(Request $request): ?Article
    {
        $postId = $request->query('p');

        if (is_scalar($postId) && ctype_digit((string) $postId)) {
            $byId = Article::publiclyVisible()
                ->whereJsonContains('editorial_metadata->wp_post_id', (int) $postId)
                ->first();

            if ($byId) {
                return $byId;
            }
        }

        $segments = array_values(array_filter(explode('/', $request->path())));
        $slug = end($segments);

        if (! is_string($slug) || $slug === '' || ! preg_match('/^[a-z0-9-]+$/', $slug)) {
            return null;
        }

        return Article::publiclyVisible()->where('slug', $slug)->first();
    }
}
