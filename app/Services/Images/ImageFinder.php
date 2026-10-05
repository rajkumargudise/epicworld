<?php

namespace App\Services\Images;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Finds a freely licensed photo for an article. Providers are tried in
 * order and the first usable hit wins:
 *
 *   1. Pexels   - needs a free API key (Admin > Settings)
 *   2. Unsplash - needs a free access key (Admin > Settings)
 *   3. Openverse - no key; only CC0 / public-domain / CC-BY results
 *
 * Every result carries the credit line its licence requires, so the
 * site can always show attribution. Failure is never an error: a
 * missing key, a network problem or no results simply returns null and
 * the article goes ahead without an image.
 */
class ImageFinder
{
    /**
     * @return array{url: string, credit: string, credit_url: ?string, source: string}|null
     */
    public function find(string $query): ?array
    {
        $query = $this->clean($query);

        if ($query === '') {
            return null;
        }

        foreach ([fn () => $this->pexels($query), fn () => $this->unsplash($query), fn () => $this->openverse($query)] as $provider) {
            try {
                $hit = $provider();
            } catch (Throwable) {
                $hit = null;
            }

            if ($hit !== null && $this->isSafeUrl($hit['url'])) {
                return $hit;
            }
        }

        return null;
    }

    private function pexels(string $query): ?array
    {
        $key = Setting::read('pexels_api_key');

        if (blank($key)) {
            return null;
        }

        $photos = Http::timeout(15)->withHeaders(['Authorization' => $key])
            ->get('https://api.pexels.com/v1/search', ['query' => $query, 'orientation' => 'landscape', 'per_page' => 5, 'size' => 'large'])
            ->json('photos') ?? [];

        $photo = $photos[0] ?? null;
        $url = $photo['src']['large2x'] ?? $photo['src']['large'] ?? null;

        if (! $photo || ! $url) {
            return null;
        }

        return [
            'url' => $url,
            'credit' => 'Photo by '.($photo['photographer'] ?? 'a Pexels photographer').' on Pexels',
            'credit_url' => $photo['url'] ?? 'https://www.pexels.com',
            'source' => 'pexels',
        ];
    }

    private function unsplash(string $query): ?array
    {
        $key = Setting::read('unsplash_access_key');

        if (blank($key)) {
            return null;
        }

        $results = Http::timeout(15)->withHeaders(['Authorization' => 'Client-ID '.$key, 'Accept-Version' => 'v1'])
            ->get('https://api.unsplash.com/search/photos', ['query' => $query, 'orientation' => 'landscape', 'per_page' => 5, 'content_filter' => 'high'])
            ->json('results') ?? [];

        $photo = $results[0] ?? null;
        $url = $photo['urls']['regular'] ?? null;

        if (! $photo || ! $url) {
            return null;
        }

        return [
            'url' => $url,
            'credit' => 'Photo by '.($photo['user']['name'] ?? 'an Unsplash photographer').' on Unsplash',
            'credit_url' => ($photo['links']['html'] ?? 'https://unsplash.com').'?utm_source=epicworld&utm_medium=referral',
            'source' => 'unsplash',
        ];
    }

    private function openverse(string $query): ?array
    {
        $response = Http::timeout(15)->withHeaders(['User-Agent' => 'EpicWorld/1.0 (+https://epicworld.in)'])
            ->get('https://api.openverse.org/v1/images/', [
                'q' => $query,
                'license' => 'cc0,pdm,by',
                'mature' => 'false',
                'aspect_ratio' => 'wide',
                'size' => 'large',
                'page_size' => 8,
            ]);

        foreach ($response->json('results') ?? [] as $image) {
            $url = $image['url'] ?? null;

            if (! $url || ! $this->isSafeUrl((string) $url) || ! in_array(strtolower((string) ($image['license'] ?? '')), ['cc0', 'pdm', 'by'], true)) {
                continue;
            }

            $license = strtoupper((string) $image['license']);
            $creator = $image['creator'] ?? null;
            $title = $image['title'] ?? 'Image';

            $credit = in_array($license, ['CC0', 'PDM'], true)
                ? trim($title.($creator ? " by {$creator}" : '').' (public domain)')
                : trim($title.($creator ? " by {$creator}" : '').' / CC BY '.($image['license_version'] ?? ''));

            return [
                'url' => $url,
                'credit' => $credit,
                'credit_url' => $image['foreign_landing_url'] ?? null,
                'source' => 'openverse',
            ];
        }

        return null;
    }

    private function clean(string $query): string
    {
        $query = preg_replace('/[^\p{L}\p{N}\s\-]/u', ' ', $query) ?? '';

        return trim(mb_substr(preg_replace('/\s+/u', ' ', $query) ?? '', 0, 100));
    }

    private function isSafeUrl(string $url): bool
    {
        return (bool) preg_match('#^https://[^\s]+$#i', $url) && strlen($url) <= 2000;
    }
}
