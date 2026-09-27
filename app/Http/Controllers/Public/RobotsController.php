<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * A configuration-driven robots.txt rather than a static public file,
 * so the Sitemap line always reflects the real, currently configured
 * application URL instead of a value that could drift from it (see
 * ForceConfiguredAppUrl). Allows normal public crawling of every
 * indexable page, keeps the admin/editorial CMS and the
 * database-native search results out of crawl budget - search is
 * already noindex per page (partials/seo.blade.php) but disallowing
 * it here too keeps crawlers from spending budget on an unbounded
 * space of query-string permutations - and points crawlers at the
 * sitemap.
 */
class RobotsController extends Controller
{
    public function index(): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /login',
            'Disallow: /search',
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n")
            ->header('Content-Type', 'text/plain');
    }
}
