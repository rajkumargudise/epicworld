<?php

namespace Tests\Feature;

use App\Enums\AutomationRunStatus;
use App\Enums\StoryStatus;
use App\Models\Source;
use App\Models\SourceFeed;
use App\Models\Story;
use App\Services\Discovery\DiscoveryRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiscoveryRunnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_ingests_active_feeds_and_qualifies_candidate_stories(): void
    {
        $feed = $this->activeFeed();
        Http::fake([$feed->url => Http::response($this->rss(), 200)]);

        $run = app(DiscoveryRunner::class)->run();

        $this->assertSame(AutomationRunStatus::Completed, $run->status);
        $this->assertSame(1, $run->items_processed);
        $this->assertSame(1, $run->items_discovered);
        $this->assertSame(1, $run->metrics['stories_qualified']);
        $this->assertSame(StoryStatus::Candidate, Story::firstOrFail()->status);
        $this->assertNotNull($feed->refresh()->last_success_at);
    }

    public function test_discovery_skips_inactive_feeds_entirely(): void
    {
        $feed = $this->activeFeed();
        $feed->update(['is_active' => false]);
        Http::preventStrayRequests();

        $run = app(DiscoveryRunner::class)->run();

        $this->assertSame(0, $run->items_processed);
        $this->assertSame(0, Story::count());
        $this->assertNull($feed->refresh()->last_fetched_at);
    }

    public function test_running_discovery_twice_does_not_duplicate_anything(): void
    {
        $feed = $this->activeFeed();
        Http::fake([$feed->url => Http::response($this->rss(), 200)]);
        $runner = app(DiscoveryRunner::class);

        $runner->run();
        $secondRun = $runner->run();

        $this->assertSame(1, Story::count());
        $this->assertSame(1, $feed->source->stories()->count());
        $this->assertSame(StoryStatus::Candidate, Story::firstOrFail()->status);
        // The story is already a candidate on the second pass, so nothing
        // qualifies again - qualification is not repeated.
        $this->assertSame(0, $secondRun->metrics['stories_qualified']);
    }

    public function test_discovery_does_not_downgrade_a_story_past_candidate(): void
    {
        $feed = $this->activeFeed();
        Http::fake([$feed->url => Http::response($this->rss(), 200)]);
        app(DiscoveryRunner::class)->run();

        Story::firstOrFail()->update(['status' => StoryStatus::Published]);

        app(DiscoveryRunner::class)->run();

        $this->assertSame(StoryStatus::Published, Story::firstOrFail()->status);
    }

    private function activeFeed(): SourceFeed
    {
        $source = Source::create(['name' => 'example-source', 'slug' => 'example-source']);

        return SourceFeed::create([
            'source_id' => $source->id,
            'name' => 'Example Feed',
            'url' => 'https://example.com/feed.xml',
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
            <title>Discovery story</title>
            <link>https://example.com/stories/discovery</link>
            <guid>discovery-1</guid>
            <pubDate>Fri, 25 Sep 2026 12:00:00 +0000</pubDate>
        </item>
    </channel>
</rss>
XML;
    }
}
