<?php

namespace Tests\Feature;

use App\Models\WireItem;
use App\Services\NewsWire\NewsWireFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NewsWireTest extends TestCase
{
    use RefreshDatabase;

    private function rss(string $items): string
    {
        return '<?xml version="1.0"?><rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/"><channel><title>T</title>'.$items.'</channel></rss>';
    }

    private function useFeeds(array $feeds): void
    {
        config(['newswire.feeds' => $feeds, 'newswire.enabled' => true]);
    }

    public function test_it_stores_headlines_with_scope_source_summary_and_image(): void
    {
        $this->useFeeds([['scope' => 'world', 'source' => 'BBC News', 'url' => 'https://feeds.example.com/world.xml']]);
        Http::fake(['feeds.example.com/*' => Http::response($this->rss(
            '<item><title>Big event &amp; more</title><link>https://news.example.com/a</link>'
            .'<description><![CDATA[<p>Something <b>happened</b> today.</p>]]></description>'
            .'<pubDate>'.now()->subMinutes(5)->toRfc2822String().'</pubDate>'
            .'<media:thumbnail url="https://img.example.com/a.jpg"/></item>'
        ))]);

        $stats = app(NewsWireFetcher::class)->run();

        $this->assertSame(1, $stats['new_items']);
        $item = WireItem::firstOrFail();
        $this->assertSame('world', $item->scope);
        $this->assertSame('BBC News', $item->source);
        $this->assertSame('Big event & more', $item->title);
        $this->assertSame('Something happened today.', $item->summary);
        $this->assertSame('https://img.example.com/a.jpg', $item->image_url);
    }

    public function test_the_same_url_is_never_stored_twice(): void
    {
        $this->useFeeds([['scope' => 'news', 'source' => 'NDTV', 'url' => 'https://feeds.example.com/n.xml']]);
        Http::fake(['feeds.example.com/*' => Http::response($this->rss('<item><title>Same</title><link>https://news.example.com/s</link></item>'))]);

        app(NewsWireFetcher::class)->run();
        $second = app(NewsWireFetcher::class)->run();

        $this->assertSame(0, $second['new_items']);
        $this->assertSame(1, WireItem::count());
    }

    public function test_one_failing_feed_does_not_stop_the_others(): void
    {
        $this->useFeeds([
            ['scope' => 'world', 'source' => 'Bad', 'url' => 'https://bad.example.com/x.xml'],
            ['scope' => 'local', 'source' => 'Good', 'url' => 'https://good.example.com/x.xml'],
        ]);
        Http::fake([
            'bad.example.com/*' => Http::response('nope', 500),
            'good.example.com/*' => Http::response($this->rss('<item><title>Local thing</title><link>https://good.example.com/1</link></item>')),
        ]);

        $stats = app(NewsWireFetcher::class)->run();

        $this->assertSame(1, $stats['failed']);
        $this->assertSame(1, $stats['new_items']);
        $this->assertSame('local', WireItem::firstOrFail()->scope);
    }

    public function test_markup_and_unsafe_urls_in_feeds_are_neutralised(): void
    {
        $this->useFeeds([['scope' => 'world', 'source' => 'X', 'url' => 'https://feeds.example.com/x.xml']]);
        Http::fake(['feeds.example.com/*' => Http::response($this->rss(
            '<item><title><![CDATA[<script>alert(1)</script>Headline]]></title><link>https://ok.example.com/1</link>'
            .'<description><![CDATA[<img src=x onerror=alert(1)>text]]></description><media:thumbnail url="javascript:alert(1)"/></item>'
            .'<item><title>Bad link</title><link>javascript:alert(1)</link></item>'
        ))]);

        app(NewsWireFetcher::class)->run();

        $this->assertSame(1, WireItem::count());
        $item = WireItem::firstOrFail();
        $this->assertStringNotContainsString('<', $item->title.$item->summary);
        $this->assertNull($item->image_url);
    }

    public function test_youtube_feed_entries_become_playable_videos(): void
    {
        $this->useFeeds([['scope' => 'world', 'source' => 'DW News', 'kind' => 'video', 'url' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UC1']]);
        Http::fake(['www.youtube.com/*' => Http::response('<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom" xmlns:yt="http://www.youtube.com/xml/schemas/2015" xmlns:media="http://search.yahoo.com/mrss/">'
            .'<entry><yt:videoId>abcDEF12345</yt:videoId><title>Video report</title><link rel="alternate" href="https://www.youtube.com/watch?v=abcDEF12345"/>'
            .'<published>'.now()->subHour()->toIso8601String().'</published><media:group><media:description>Desc here</media:description></media:group></entry>'
            .'<entry><yt:videoId>bad id!</yt:videoId><title>Broken</title><link rel="alternate" href="https://www.youtube.com/watch?v=x"/></entry></feed>')]);

        app(NewsWireFetcher::class)->run();

        $video = WireItem::videos()->firstOrFail();
        $this->assertSame('abcDEF12345', $video->video_id);
        $this->assertTrue($video->isVideo());
        $this->assertStringContainsString('abcDEF12345', $video->thumbnail());
        $this->assertSame(1, WireItem::count());
    }

    public function test_old_items_are_pruned(): void
    {
        $this->useFeeds([]);
        WireItem::create(['kind' => 'article', 'scope' => 'world', 'source' => 'S', 'title' => 'Old', 'url' => 'https://x.test/old', 'url_hash' => hash('sha256', 'old'), 'published_at' => now()->subDays(30)]);
        WireItem::create(['kind' => 'article', 'scope' => 'world', 'source' => 'S', 'title' => 'New', 'url' => 'https://x.test/new', 'url_hash' => hash('sha256', 'new'), 'published_at' => now()]);

        $stats = app(NewsWireFetcher::class)->run();

        $this->assertSame(1, $stats['pruned']);
        $this->assertSame(['New'], WireItem::pluck('title')->all());
    }

    public function test_live_pages_render_and_the_brief_page_is_noindex(): void
    {
        $item = WireItem::create(['kind' => 'article', 'scope' => 'local', 'source' => 'The Hindu', 'title' => 'Hyderabad metro update', 'summary' => 'Short brief.', 'url' => 'https://x.test/h', 'url_hash' => hash('sha256', 'h'), 'published_at' => now()]);

        $this->get('/live')->assertOk()->assertSee('Hyderabad metro update');
        $this->get('/live/local')->assertOk()->assertSee('Hyderabad metro update');
        $this->get('/live/videos')->assertOk()->assertSee('Live TV');
        $this->get('/live/bogus')->assertNotFound();
        $this->get('/live/local/feed?variant=list')->assertOk()->assertSee('Hyderabad metro update')->assertHeader('X-Robots-Tag', 'noindex');
        $this->get($item->path())->assertOk()->assertSee('Short brief.')->assertSee('Read full report')->assertSee('noindex', false);
    }

    public function test_the_home_page_leads_with_live_news(): void
    {
        WireItem::create(['kind' => 'article', 'scope' => 'world', 'source' => 'BBC News', 'title' => 'Global summit opens', 'url' => 'https://x.test/g', 'url_hash' => hash('sha256', 'g'), 'published_at' => now()]);

        $this->get('/')->assertOk()->assertSee('Live news')->assertSee('Global summit opens')->assertSee('Live TV');
    }
}
