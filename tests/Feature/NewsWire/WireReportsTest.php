<?php

namespace Tests\Feature\NewsWire;

use App\Models\WireItem;
use App\Services\Ai\AiResult;
use App\Services\Ai\Providers\FakeAiProvider;
use App\Services\NewsWire\LiveChannelResolver;
use App\Services\NewsWire\NewsWireFetcher;
use App\Services\NewsWire\WireReportWriter;
use App\Services\NewsWire\YoutubeScraper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WireReportsTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.default' => 'fake']);
        Cache::flush();
    }

    private function headline(array $overrides = []): WireItem
    {
        return WireItem::create($overrides + [
            'kind' => 'article', 'scope' => 'news', 'source' => 'The Hindu',
            'title' => 'Parliament passes the new data protection amendment bill',
            'summary' => 'The Lok Sabha passed the amendment on Tuesday after a short debate.',
            'url' => 'https://example.test/story-1', 'url_hash' => hash('sha256', 'story-1'),
            'published_at' => now()->subMinutes(20),
        ]);
    }

    private function report(string $extra = 'Lawmakers debated enforcement powers, penalties and exemptions for startups before the vote concluded late in the evening.'): array
    {
        $body = "The amendment to the data protection law cleared the lower house this week, marking a notable step in how personal information is regulated.\n\n## What happened\n\n"
            .str_repeat($extra.' ', 3)."\n\n## Background\n\n"
            .str_repeat('Data protection rules decide how companies may collect, store and share the details people hand over online, and why consent matters. ', 6)
            ."\n\n## Why it matters\n\n"
            .str_repeat('Citizens, banks, hospitals and technology firms all handle sensitive records every day, so changes to the framework reach almost everyone. ', 6)
            ."\n\n## Bottom line\n\nThe change will take effect only after the upper house and the president sign off.";

        return ['dek' => 'Parliament clears an amendment to the data protection law.', 'body' => $body, 'key_points' => ['The amendment passed the lower house.', 'It still needs upper house approval.']];
    }

    public function test_feed_times_in_other_time_zones_are_stored_in_utc(): void
    {
        $at = now()->subHour();
        config(['newswire.feeds' => [['scope' => 'news', 'source' => 'The Hindu', 'url' => 'https://feed.test/rss']]]);
        Http::fake(['feed.test/*' => Http::response('<?xml version="1.0"?><rss version="2.0"><channel><item><title>Something happened in Delhi today</title><link>https://example.test/a</link>'
            .'<pubDate>'.$at->copy()->timezone('Asia/Kolkata')->format('D, d M Y H:i:s O').'</pubDate></item></channel></rss>')]);

        app(NewsWireFetcher::class)->run();

        $this->assertEqualsWithDelta($at->timestamp, WireItem::firstOrFail()->published_at->timestamp, 5);
    }

    public function test_the_writer_stores_an_original_report_and_the_page_shows_it_without_sending_readers_away(): void
    {
        Http::fake(['example.test/*' => Http::response('<html><body><article><p>'.str_repeat('Unrelated page text that the writer reads privately. ', 20).'</p></article></body></html>')]);
        $item = $this->headline();
        app(FakeAiProvider::class)->push(AiResult::success('fake', 'm', $this->report()));

        $this->assertTrue(app(WireReportWriter::class)->write($item));

        $item->refresh();
        $this->assertSame('ready', $item->report_status);

        $html = $this->get($item->path())->assertOk()
            ->assertSee('Key takeaways')
            ->assertSee('Why it matters')
            ->assertSee('Written by EpicWorld in its own words')
            ->assertDontSee('Read full report')
            ->getContent();

        // The source is credited, but only as a small link - not the way to "read the story".
        $this->assertStringContainsString($item->url, $html);
    }

    public function test_a_draft_that_copies_the_source_wording_is_rejected(): void
    {
        $copied = 'The Lok Sabha passed the amendment on Tuesday after a short debate and members of several parties welcomed the decision while others raised concerns about enforcement powers and penalties for smaller companies.';
        Http::fake(['example.test/*' => Http::response('<html><body><article><p>'.$copied.' '.str_repeat('More private text here. ', 30).'</p></article></body></html>')]);
        $item = $this->headline(['summary' => $copied]);
        app(FakeAiProvider::class)->push(AiResult::success('fake', 'm', $this->report($copied)));

        $this->assertFalse(app(WireReportWriter::class)->write($item));

        $item->refresh();
        $this->assertNull($item->report);
        $this->assertStringContainsString('original wording', (string) $item->report_error);
    }

    public function test_opening_a_headline_writes_its_report_on_demand_but_crawlers_do_not_trigger_it(): void
    {
        Http::fake(['example.test/*' => Http::response('<html></html>')]);
        $item = $this->headline();
        app(FakeAiProvider::class)->push(AiResult::success('fake', 'm', $this->report()));

        $this->withHeaders(['User-Agent' => 'Googlebot/2.1'])->get($item->path())->assertOk();
        $this->assertNull($item->refresh()->report_status);

        $this->withHeaders(['User-Agent' => self::BROWSER])->get($item->path())->assertOk()->assertSee('Writing the full report');

        // The report is written once the response has gone out; the next view shows it.
        $this->assertSame('ready', $item->refresh()->report_status);
        $this->withHeaders(['User-Agent' => self::BROWSER])->get($item->path())->assertSee('Key takeaways');
        $this->getJson(route('wire.report', $item))->assertJson(['status' => 'ready']);
    }

    public function test_a_rate_limited_provider_leaves_the_headline_for_later(): void
    {
        Http::fake(['example.test/*' => Http::response('<html></html>')]);
        $item = $this->headline();
        app(FakeAiProvider::class)->push(AiResult::failure(\App\Enums\AiResultStatus::RateLimited, 'fake', 'm', 'quota'));

        $this->expectException(\App\Services\Ai\Exceptions\AiProviderUnavailableException::class);

        try {
            app(WireReportWriter::class)->write($item);
        } finally {
            $item->refresh();
            $this->assertNull($item->report_status);
            $this->assertSame(0, (int) $item->report_attempts);
        }
    }

    public function test_the_scheduled_command_writes_reports_for_the_top_headlines(): void
    {
        Http::fake(['example.test/*' => Http::response('<html></html>')]);
        $item = $this->headline();
        app(FakeAiProvider::class)->push(AiResult::success('fake', 'm', $this->report()));

        $this->artisan('wire:write-reports', ['--limit' => 1])->assertSuccessful();

        $this->assertSame('ready', $item->refresh()->report_status);
    }

    public function test_only_channels_that_are_on_air_and_embeddable_are_shown_and_they_play_the_exact_stream(): void
    {
        config(['newswire.live_channels' => [
            ['name' => 'Channel On', 'channel_id' => 'UC'.str_repeat('a', 22), 'scope' => 'news', 'featured' => true],
            ['name' => 'Channel Off', 'channel_id' => 'UC'.str_repeat('b', 22), 'scope' => 'news'],
            ['name' => 'Channel NoEmbed', 'channel_id' => 'UC'.str_repeat('c', 22), 'scope' => 'news'],
        ]]);

        $page = fn (string $id, bool $live) => $live ? '"currentVideoEndpoint":{"watchEndpoint":{"videoId":"'.$id.'"}} "isLive":true' : '<html>not streaming</html>';
        Http::fake([
            'www.youtube.com/channel/UC'.str_repeat('a', 22).'/live' => Http::response($page('liveAAAAAAA', true)),
            'www.youtube.com/channel/UC'.str_repeat('b', 22).'/live' => Http::response($page('liveBBBBBBB', false)),
            'www.youtube.com/channel/UC'.str_repeat('c', 22).'/live' => Http::response($page('liveCCCCCCC', true)),
            'www.youtube.com/oembed*' => fn ($request) => str_contains($request->url(), 'liveCCCCCCC') ? Http::response('', 401) : Http::response(['ok' => 1]),
        ]);

        $map = app(LiveChannelResolver::class)->refresh();

        $this->assertSame('liveAAAAAAA', $map['UC'.str_repeat('a', 22)]);
        $this->assertNull($map['UC'.str_repeat('b', 22)]);
        $this->assertNull($map['UC'.str_repeat('c', 22)]);

        $this->get('/')->assertOk()
            ->assertSee('Channel On')
            ->assertSee('embed/liveAAAAAAA', false)
            ->assertDontSee('Channel Off')
            ->assertDontSee('Channel NoEmbed');
    }

    public function test_the_scraper_finds_a_live_video_only_when_the_channel_is_streaming(): void
    {
        $scraper = app(YoutubeScraper::class);

        $this->assertSame('abcDEF12345', $scraper->liveVideoId('"currentVideoEndpoint":{"clickTrackingParams":"x","watchEndpoint":{"videoId":"abcDEF12345"}} "isLive":true'));
        $this->assertNull($scraper->liveVideoId('"currentVideoEndpoint":{"watchEndpoint":{"videoId":"abcDEF12345"}} (a past stream)'));
        $this->assertNull($scraper->liveVideoId('<html>channel page</html>'));
    }
}
