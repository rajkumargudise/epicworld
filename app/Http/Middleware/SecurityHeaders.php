<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Milestone 17: a small, deliberately conservative set of response
 * headers - only the ones that are safe on every page of this
 * application with zero risk of breaking something, unlike a
 * Content-Security-Policy (which would need to allowlist Vite's
 * assets, Google Fonts, source-attribution links out to arbitrary
 * publishers, and a future AdSense integration - not something to
 * get right without dedicated testing) or Strict-Transport-Security
 * (which must never be sent until HTTPS in production is confirmed,
 * per this milestone's own instructions - enabling it prematurely
 * can lock out a domain that briefly serves plain HTTP). Both are
 * left for a deployment-specific follow-up once the real Hostinger
 * HTTPS setup and asset origins are confirmed, not invented here.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // SAMEORIGIN, not DENY: the admin CMS and public site never
        // need to be framed by another origin, but nothing in this
        // application frames itself either, so this only removes a
        // capability (clickjacking via a third-party iframe) without
        // touching one this app actually uses.
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        // A conservative default that only matters for APIs/features
        // this application doesn't use (geolocation, camera, etc.) -
        // never restricts anything the site itself does.
        $response->headers->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=()');

        // HTTPS-only for 6 months on the real site. Deliberately no includeSubDomains/preload:
        // other sites on this hosting account share the domain.
        if ($request->isSecure() && ! str_contains($request->getHost(), 'staging')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=15552000');
        }

        // A staging address must never end up in search results.
        if (str_contains($request->getHost(), 'staging') || str_contains((string) parse_url((string) config('app.url'), PHP_URL_HOST), 'staging')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
