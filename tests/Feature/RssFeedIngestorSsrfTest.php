<?php

namespace Tests\Feature;

use App\Models\Source;
use App\Models\SourceFeed;
use App\Services\Feeds\RssFeedIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A feed URL is operator-configured, not public input, but discovery
 * runs unattended on a schedule - so a URL that was mistyped, or a
 * source row that was ever compromised or misconfigured, must never
 * be able to turn a scheduled run into a request against this
 * server's own network or a cloud metadata endpoint.
 */
class RssFeedIngestorSsrfTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<array{0: string}>
     */
    public static function internalUrls(): array
    {
        return [
            'loopback IPv4' => ['http://127.0.0.1/feed.xml'],
            'loopback IPv4 alt' => ['http://127.0.0.53/feed.xml'],
            'loopback IPv6' => ['http://[::1]/feed.xml'],
            'private class A' => ['http://10.0.0.5/feed.xml'],
            'private class B' => ['http://172.16.4.9/feed.xml'],
            'private class C' => ['http://192.168.1.1/feed.xml'],
            'link-local / cloud metadata' => ['http://169.254.169.254/latest/meta-data/'],
            'unspecified' => ['http://0.0.0.0/feed.xml'],
            'localhost hostname' => ['http://localhost/feed.xml'],
            'localhost with port' => ['http://localhost:8080/feed.xml'],
            '.local hostname' => ['http://printer.local/feed.xml'],
            '.internal hostname' => ['http://db.internal/feed.xml'],
        ];
    }

    #[DataProvider('internalUrls')]
    public function test_a_feed_url_targeting_an_internal_address_is_rejected(string $url): void
    {
        Http::preventStrayRequests();
        $feed = $this->makeFeed($url);

        $stories = app(RssFeedIngestor::class)->ingest($feed);
        $feed->refresh();

        $this->assertCount(0, $stories);
        $this->assertSame(
            'Feed URL must not target a private, loopback, or internal address.',
            $feed->last_error
        );
        $this->assertNotNull($feed->last_failure_at);
    }

    public function test_a_genuine_public_feed_url_is_not_affected_by_the_ssrf_guard(): void
    {
        $feed = $this->makeFeed('https://example.com/feed.xml');
        Http::fake([$feed->url => Http::response($this->rss(), 200)]);

        $stories = app(RssFeedIngestor::class)->ingest($feed);

        $this->assertCount(1, $stories);
        $this->assertNull($feed->refresh()->last_error);
    }

    /**
     * Milestone 17: validateUrl() only checks the URL we were given -
     * a feed that passes that check could still redirect to a private
     * or internal address at request time. Laravel's HTTP client
     * follows redirects by default, so this proves the ingestor
     * disables that and fails closed on a 3xx instead of silently
     * following it anywhere.
     */
    public function test_a_feed_url_that_redirects_is_rejected_rather_than_followed(): void
    {
        $feed = $this->makeFeed('https://example.com/feed.xml');
        Http::fake([
            $feed->url => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
        ]);

        $stories = app(RssFeedIngestor::class)->ingest($feed);
        $feed->refresh();

        $this->assertCount(0, $stories);
        $this->assertStringContainsString('redirected', $feed->last_error);
        $this->assertNotNull($feed->last_failure_at);
    }

    public function test_an_oversized_feed_response_is_rejected(): void
    {
        $feed = $this->makeFeed('https://example.com/feed.xml');
        Http::fake([$feed->url => Http::response(str_repeat('a', 5_000_001), 200)]);

        $stories = app(RssFeedIngestor::class)->ingest($feed);
        $feed->refresh();

        $this->assertCount(0, $stories);
        $this->assertStringContainsString('5 MB limit', $feed->last_error);
    }

    private function makeFeed(string $url): SourceFeed
    {
        $source = Source::create(['name' => 'ssrf-test-source', 'slug' => 'ssrf-test-source-'.uniqid()]);

        return SourceFeed::create([
            'source_id' => $source->id,
            'name' => 'SSRF Test Feed',
            'url' => $url,
        ]);
    }

    private function rss(): string
    {
        return <<<'XML'
<?xml version="1.0"?>
<rss version="2.0">
    <channel>
        <title>Example Feed</title>
        <item>
            <title>Public feed story</title>
            <link>https://example.com/stories/public</link>
            <guid>public-1</guid>
        </item>
    </channel>
</rss>
XML;
    }
}
