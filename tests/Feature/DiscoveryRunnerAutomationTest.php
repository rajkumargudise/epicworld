<?php

namespace Tests\Feature;

use App\Enums\AutomationRunStatus;
use App\Models\Article;
use App\Models\Source;
use App\Models\SourceFeed;
use App\Models\Story;
use App\Services\Ai\Providers\FakeAiProvider;
use App\Services\Discovery\DiscoveryRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * DiscoveryRunnerTest already covers the core idempotency behavior
 * (matching, qualification, job creation). This file covers the
 * Milestone 15 additions on top of it: per-feed failure isolation,
 * accurate AutomationRun counters/timestamps, and the "discovery
 * never touches AI or publication" boundary.
 */
class DiscoveryRunnerAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_multi_feed_run_processes_every_active_feed(): void
    {
        $feedA = $this->activeFeed('source-a');
        $feedB = $this->activeFeed('source-b');
        Http::fake([
            $feedA->url => Http::response($this->rss('Story A', 'story-a'), 200),
            $feedB->url => Http::response($this->rss('Story B', 'story-b'), 200),
        ]);

        $run = app(DiscoveryRunner::class)->run();

        $this->assertSame(AutomationRunStatus::Completed, $run->status);
        $this->assertSame(2, $run->items_processed);
        $this->assertSame(2, $run->items_discovered);
        $this->assertSame(0, $run->items_failed);
        $this->assertSame(2, Story::count());
        $this->assertNotNull($feedA->refresh()->last_success_at);
        $this->assertNotNull($feedB->refresh()->last_success_at);
    }

    public function test_one_failing_feed_does_not_stop_discovery_for_the_others(): void
    {
        $badFeed = $this->activeFeed('bad-source');
        $goodFeed = $this->activeFeed('good-source');
        Http::fake([
            $badFeed->url => Http::response('unavailable', 503),
            $goodFeed->url => Http::response($this->rss('Good story', 'good-1'), 200),
        ]);

        $run = app(DiscoveryRunner::class)->run();

        $this->assertSame(AutomationRunStatus::Partial, $run->status);
        $this->assertSame(2, $run->items_processed);
        $this->assertSame(1, $run->items_failed);
        $this->assertSame(1, Story::count());
        $this->assertDatabaseHas('stories', ['title' => 'Good story']);
        $this->assertNotNull($badFeed->refresh()->last_failure_at);
        $this->assertNotNull($goodFeed->refresh()->last_success_at);
    }

    public function test_inactive_feeds_are_counted_as_skipped_rather_than_processed(): void
    {
        $active = $this->activeFeed('active-source');
        $inactive = $this->activeFeed('inactive-source');
        $inactive->update(['is_active' => false]);

        Http::fake([$active->url => Http::response($this->rss('Only active story', 'only-active'), 200)]);
        Http::preventStrayRequests();

        $run = app(DiscoveryRunner::class)->run();

        $this->assertSame(1, $run->items_processed);
        $this->assertSame(1, $run->items_skipped);
    }

    public function test_automation_run_records_accurate_counters_and_timestamps(): void
    {
        $feed = $this->activeFeed('timed-source');
        Http::fake([$feed->url => Http::response($this->rss('Timed story', 'timed-1'), 200)]);

        $this->travelTo('2026-09-27 10:00:00');
        $run = app(DiscoveryRunner::class)->run();

        $this->assertSame('2026-09-27 10:00:00', $run->started_at->format('Y-m-d H:i:s'));
        $this->assertNotNull($run->completed_at);
        $this->assertTrue($run->completed_at->greaterThanOrEqualTo($run->started_at));
        $this->assertSame(1, $run->items_processed);
        $this->assertSame(1, $run->items_discovered);
        $this->assertSame(0, $run->items_failed);
        $this->assertSame(0, $run->items_skipped);
        $this->assertSame(1, $run->metrics['stories_qualified']);
        $this->assertSame(1, $run->metrics['editorial_jobs_created']);
    }

    public function test_discovery_never_invokes_the_ai_provider(): void
    {
        $feed = $this->activeFeed('ai-check-source');
        Http::fake([$feed->url => Http::response($this->rss('AI check story', 'ai-check-1'), 200)]);

        app(DiscoveryRunner::class)->run();

        $this->assertSame(0, app(FakeAiProvider::class)->callCount());
    }

    public function test_discovery_never_publishes_an_article(): void
    {
        $feed = $this->activeFeed('publish-check-source');
        Http::fake([$feed->url => Http::response($this->rss('Publish check story', 'publish-check-1'), 200)]);

        app(DiscoveryRunner::class)->run();

        $this->assertSame(0, Article::count());
    }

    private function activeFeed(string $sourceSlug): SourceFeed
    {
        $source = Source::create(['name' => $sourceSlug, 'slug' => $sourceSlug.'-'.uniqid()]);

        return SourceFeed::create([
            'source_id' => $source->id,
            'name' => 'Feed for '.$sourceSlug,
            'url' => "https://{$sourceSlug}.example.com/feed.xml",
        ]);
    }

    private function rss(string $title, string $guid): string
    {
        return <<<XML
<?xml version="1.0"?>
<rss version="2.0">
    <channel>
        <title>Example Feed</title>
        <item>
            <title>{$title}</title>
            <link>https://example.com/stories/{$guid}</link>
            <guid>{$guid}</guid>
            <pubDate>Fri, 25 Sep 2026 12:00:00 +0000</pubDate>
        </item>
    </channel>
</rss>
XML;
    }
}
