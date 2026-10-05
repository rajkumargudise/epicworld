<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Typed, validated read access to the site's admin-managed integration
 * settings (analytics, Search Console, AdSense, consent, comments).
 * Values are memoised per request and every getter returns null/false
 * for anything that doesn't match the expected format, so a bad stored
 * value can never be echoed into a page as script or markup.
 */
class SiteSettings
{
    /** @var array<string, ?string> */
    private array $cache = [];

    private function get(string $key): ?string
    {
        if (! array_key_exists($key, $this->cache)) {
            $this->cache[$key] = Setting::read($key);
        }

        return $this->cache[$key];
    }

    public function flush(): void
    {
        $this->cache = [];
    }

    /** GA4 measurement ID, e.g. G-ABC123DEF4. */
    public function ga4Id(): ?string
    {
        $id = $this->get('ga4_measurement_id');

        return $id !== null && preg_match('/^G-[A-Z0-9]{6,14}$/', $id) ? $id : null;
    }

    /** Search Console HTML-tag verification token. */
    public function searchConsoleToken(): ?string
    {
        $token = $this->get('gsc_verification');

        return $token !== null && preg_match('/^[A-Za-z0-9_\-]{20,100}$/', $token) ? $token : null;
    }

    /** AdSense publisher ID, e.g. ca-pub-1234567890123456. */
    public function adsensePublisherId(): ?string
    {
        $id = $this->get('adsense_publisher_id');

        return $id !== null && preg_match('/^ca-pub-\d{10,20}$/', $id) ? $id : null;
    }

    /** The AdSense publisher ID in ads.txt form (pub-...). */
    public function adsTxtId(): ?string
    {
        $id = $this->adsensePublisherId();

        return $id === null ? null : substr($id, 3);
    }

    /** Ads only run when explicitly switched on AND an ID is configured. */
    public function adsEnabled(): bool
    {
        return $this->adsensePublisherId() !== null && $this->get('adsense_enabled') === '1';
    }

    public function analyticsEnabled(): bool
    {
        return $this->ga4Id() !== null;
    }

    /** Whether any consent-gated script (analytics/ads) is configured. */
    public function needsConsent(): bool
    {
        return $this->analyticsEnabled() || $this->adsEnabled();
    }

    public function commentsEnabled(): bool
    {
        return $this->get('comments_enabled') !== '0';
    }

    public function contactEmail(): ?string
    {
        $email = $this->get('contact_email');

        return $email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
