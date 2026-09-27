<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public canonical URLs, sitemap entries, robots.txt, and JSON-LD all
 * need one consistent URL for a given page - the one actually
 * configured in APP_URL, not whatever Host header a proxy or load
 * balancer happens to forward on shared hosting. Forcing the root
 * (and, when APP_URL itself is https://, the scheme) here means every
 * route()/url() call downstream - in controllers and in Blade - is
 * automatically consistent, without hardcoding the domain in any
 * template. This runs for every request rather than only in
 * production, so local/staging environments simply get whatever they
 * themselves configured in APP_URL - never a hardcoded domain.
 */
class ForceConfiguredAppUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredUrl = config('app.url');

        if (is_string($configuredUrl) && $configuredUrl !== '') {
            URL::forceRootUrl($configuredUrl);

            if (str_starts_with($configuredUrl, 'https://')) {
                URL::forceScheme('https');
            }
        }

        return $next($request);
    }
}
