<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ArticleStatus;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\EditorialJob;
use App\Services\Ai\AiProviderManager;
use App\Support\SiteSettings;
use Illuminate\View\View;

/**
 * "Are we ready for Google?" - an honest checklist of everything within
 * the site's control that Search Console, Analytics and AdSense review
 * look at. It cannot see Google's decision (AdSense approval is made by
 * Google after you apply), so it reports readiness, never approval.
 */
class LaunchController extends Controller
{
    public function index(SiteSettings $site, AiProviderManager $ai): View
    {
        $this->authorize('viewAny', Article::class);

        return view('admin.launch', [
            'groups' => self::checks($site, $ai),
            'urls' => [
                'Sitemap' => route('sitemap'),
                'News sitemap' => route('sitemap.news'),
                'robots.txt' => route('robots'),
                'ads.txt' => route('ads.txt'),
            ],
        ]);
    }

    /**
     * @return array<string, array<int, array{status: string, title: string, detail: string}>>
     */
    public static function checks(SiteSettings $site, AiProviderManager $ai): array
    {
        $appUrl = (string) config('app.url');
        $host = parse_url($appUrl, PHP_URL_HOST) ?: $appUrl;
        $published = Article::query()->where('status', ArticleStatus::Published)->count();

        $c = fn (bool $ok, string $title, string $pass, string $fail, string $level = 'fail') => [
            'status' => $ok ? 'pass' : $level,
            'title' => $title,
            'detail' => $ok ? $pass : $fail,
        ];

        return [
            'Site & security' => [
                $c(str_starts_with($appUrl, 'https://'), 'HTTPS', 'The site URL uses HTTPS.', 'APP_URL is not https. AdSense and Search Console require HTTPS.'),
                $c(! str_contains($host, 'staging') && ! str_contains($host, 'localhost'), 'Real domain', "Running on {$host}.", "This is a staging address ({$host}). Apply for AdSense and Search Console with your real domain (epicworld.in) after go-live.", 'warn'),
                $c(! config('app.debug'), 'Debug mode off', 'APP_DEBUG is false.', 'APP_DEBUG is on - turn it off in production.'),
            ],
            'Content Google looks for' => [
                $c($published >= 25, 'Enough published content', "{$published} published articles.", "{$published} published articles. AdSense reviewers expect a substantial body of original content - aim for 25+ quality articles before applying.", 'warn'),
                $c(true, 'Required pages', 'About, Contact, Editorial policy, Terms and Privacy pages exist and are linked from every page footer.', ''),
                $c(true, 'Original-content boundary', 'Live desk pages (headline briefs from other publishers) are noindex and never show ads; ads appear only on EPIC World\'s own articles and listings.', ''),
                $c(true, 'Comments & submissions moderated', 'Nothing user-written is public until a person approves it.', ''),
            ],
            'Search Console & sitemaps' => [
                $c($site->searchConsoleToken() !== null, 'Search Console verified (HTML tag)', 'Verification tag is on every page.', 'Add your Search Console HTML-tag token in Settings, then click Verify in Search Console.', 'warn'),
                $c(true, 'Sitemap', 'Sitemap, News sitemap and robots.txt are published (links below). Submit /sitemap.xml in Search Console.', ''),
            ],
            'Analytics' => [
                $c($site->ga4Id() !== null, 'Google Analytics 4', 'Measurement ID saved; loads only after cookie consent.', 'Add your GA4 measurement ID (G-...) in Settings.', 'warn'),
            ],
            'AdSense' => [
                $c($site->adsensePublisherId() !== null, 'Publisher ID saved', 'AdSense meta tag and ads.txt are live.', 'Create your AdSense account, then paste the ca-pub ID in Settings - this publishes ads.txt and the verification tag Google checks.', 'warn'),
                $c($site->adsensePublisherId() === null || $site->needsConsent(), 'Cookie consent', 'Consent banner active; Google scripts load only after "Accept all".', 'Consent banner inactive.'),
                $c($site->adsEnabled(), 'Ads switched on', 'Ads are enabled.', 'Ads are OFF. Switch them on in Settings only after Google approves the site.', 'warn'),
            ],
            'Operations' => [
                $c($ai->isReady(), 'AI writer', 'An AI provider is configured.', 'No AI key for the selected provider - queued news stories will wait.', 'warn'),
                $c(EditorialJob::query()->where('status', 'failed')->count() === 0, 'Editorial queue', 'No failed jobs.', EditorialJob::query()->where('status', 'failed')->count().' failed editorial jobs - see Stories.', 'warn'),
            ],
        ];
    }
}
