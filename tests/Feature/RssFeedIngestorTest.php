<?php

namespace Tests\Feature;

use App\Enums\StoryStatus;
use App\Models\Source;
use App\Models\SourceFeed;
use App\Models\Story;
use App\Services\Feeds\RssFeedIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RssFeedIngestorTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_rss_ingestion_creates_a_story_and_source_pivot(): void
    {
        $feed = $this->makeFeed();
        Http::fake([$feed->url => Http::response($this->rss(), 200)]);

        $stories = app(RssFeedIngestor::class)->ingest($feed);

        $this->assertCount(1, $stories);
        $this->assertDatabaseHas('stories', [
            'title' => 'First story',
            'status' => 'discovered',
        ]);
        $story = Story::firstOrFail();
        $this->assertMatchesRegularExpression('/^first-story-[a-f0-9]{12}$/', $story->slug);
        $this->assertSame(64, strlen($story->content_hash));
        $this->assertDatabaseHas('source_story', [
            'source_id' => $feed->source_id,
            'source_url' => 'https://example.com/stories/first',
            'external_id' => 'story-1',
        ]);
    }

    public function test_published_date_is_normalized_and_feed_success_is_recorded(): void
    {
        $feed = $this->makeFeed();
        Http::fake([$feed->url => Http::response($this->rss(), 200)]);

        app(RssFeedIngestor::class)->ingest($feed);
        $feed->refresh();
        $story = Story::firstOrFail();

        $this->assertSame('2026-09-25 12:00:00', $story->occurred_at->format('Y-m-d H:i:s'));
        $this->assertNotNull($feed->last_fetched_at);
        $this->assertNotNull($feed->last_success_at);
        $this->assertNull($feed->last_failure_at);
        $this->assertNull($feed->last_error);
    }

    public function test_processing_the_same_rss_item_twice_does_not_duplicate_the_story(): void
    {
        $feed = $this->makeFeed();
        Http::fake([$feed->url => Http::response($this->rss(), 200)]);
        $ingestor = app(RssFeedIngestor::class);

        $ingestor->ingest($feed);
        $ingestor->ingest($feed);

        $this->assertSame(1, Story::count());
        $this->assertSame(1, $feed->source->stories()->count());
    }

    public function test_http_failure_records_feed_failure(): void
    {
        $feed = $this->makeFeed();
        Http::fake([$feed->url => Http::response('unavailable', 503)]);

        $stories = app(RssFeedIngestor::class)->ingest($feed);
        $feed->refresh();

        $this->assertCount(0, $stories);
        $this->assertNotNull($feed->last_fetched_at);
        $this->assertNotNull($feed->last_failure_at);
        $this->assertSame('Feed request returned HTTP 503.', $feed->last_error);
    }

    public function test_malformed_feed_fails_safely_and_records_the_error(): void
    {
        $feed = $this->makeFeed();
        Http::fake([$feed->url => Http::response('<not-valid>', 200)]);

        $stories = app(RssFeedIngestor::class)->ingest($feed);
        $feed->refresh();

        $this->assertCount(0, $stories);
        $this->assertSame(0, Story::count());
        $this->assertSame('Feed content is not valid XML.', $feed->last_error);
    }

    public function test_unsupported_feed_fails_safely_and_records_the_error(): void
    {
        $feed = $this->makeFeed();
        Http::fake([$feed->url => Http::response('<document><item /></document>', 200)]);

        $stories = app(RssFeedIngestor::class)->ingest($feed);
        $feed->refresh();

        $this->assertCount(0, $stories);
        $this->assertSame('Feed format is unsupported.', $feed->last_error);
    }

    public function test_non_http_feed_url_fails_safely(): void
    {
        $feed = $this->makeFeed();
        $feed->update(['url' => 'file:///tmp/feed.xml']);
        Http::preventStrayRequests();

        $stories = app(RssFeedIngestor::class)->ingest($feed);
        $feed->refresh();

        $this->assertCount(0, $stories);
        $this->assertSame('Feed URL must use HTTP or HTTPS.', $feed->last_error);
    }

    public function test_atom_ingestion_creates_a_story(): void
    {
        $feed = $this->makeFeed();
        Http::fake([$feed->url => Http::response($this->atom(), 200)]);

        app(RssFeedIngestor::class)->ingest($feed);

        $this->assertDatabaseHas('stories', [
            'title' => 'Atom story',
            'canonical_url' => 'https://example.com/atom-story',
        ]);
    }

    public function test_existing_story_status_is_preserved_on_reingestion(): void
    {
        $feed = $this->makeFeed();
        Http::fake([$feed->url => Http::response($this->rss(), 200)]);
        $ingestor = app(RssFeedIngestor::class);

        $ingestor->ingest($feed);
        $story = Story::firstOrFail();
        $story->update(['status' => StoryStatus::Candidate]);
        $ingestor->ingest($feed);

        $this->assertSame(StoryStatus::Candidate, $story->refresh()->status);
    }

    public function test_existing_story_first_seen_at_is_preserved_while_last_seen_at_changes(): void
    {
        $feed = $this->makeFeed();
        Http::fake([$feed->url => Http::response($this->rss(), 200)]);
        $ingestor = app(RssFeedIngestor::class);

        $this->travelTo('2026-09-25 12:00:00');
        $ingestor->ingest($feed);
        $story = Story::firstOrFail();
        $firstSeenAt = $story->first_seen_at;

        $this->travelTo('2026-09-25 13:00:00');
        $ingestor->ingest($feed);
        $story->refresh();

        $this->assertTrue($story->first_seen_at->equalTo($firstSeenAt));
        $this->assertSame('2026-09-25 13:00:00', $story->last_seen_at->format('Y-m-d H:i:s'));
    }

    public function test_invalid_publication_date_does_not_abort_the_feed(): void
    {
        $feed = $this->makeFeed();
        Http::fake([$feed->url => Http::response($this->rssItems(<<<'XML'
        <item>
            <title>Invalid date story</title>
            <link>https://example.com/invalid-date</link>
            <guid>invalid-date</guid>
            <pubDate>not-a-date</pubDate>
        </item>
        XML), 200)]);

        $stories = app(RssFeedIngestor::class)->ingest($feed);

        $this->assertCount(1, $stories);
        $this->assertNull(Story::firstOrFail()->occurred_at);
        $this->assertNotNull($feed->refresh()->last_success_at);
    }

    public function test_invalid_item_does_not_prevent_valid_items_from_being_imported(): void
    {
        $feed = $this->makeFeed();
        Http::fake([$feed->url => Http::response($this->rssItems(<<<'XML'
        <item>
            <title>Valid first story</title>
            <guid>valid-first</guid>
        </item>
        <item>
            <description>Missing title</description>
            <guid>invalid-item</guid>
        </item>
        <item>
            <title>Valid second story</title>
            <guid>valid-second</guid>
        </item>
        XML), 200)]);

        $stories = app(RssFeedIngestor::class)->ingest($feed);

        $this->assertCount(2, $stories);
        $this->assertSame(2, Story::count());
        $this->assertNotNull($feed->refresh()->last_success_at);
    }

    public function test_reingestion_does_not_erase_stronger_existing_story_data(): void
    {
        $feed = $this->makeFeed();
        Http::fakeSequence()
            ->push($this->rss(), 200)
            ->push($this->rssItems(<<<'XML'
        <item>
            <title>First story</title>
            <guid>story-1</guid>
        </item>
        XML), 200);
        $ingestor = app(RssFeedIngestor::class);

        $ingestor->ingest($feed);
        $story = Story::firstOrFail();
        $story->update([
            'summary' => 'Editorially enriched summary.',
            'canonical_url' => 'https://canonical.example.com/first',
            'occurred_at' => '2026-09-24 12:00:00',
        ]);

        $ingestor->ingest($feed);
        $story->refresh();

        $this->assertSame('Editorially enriched summary.', $story->summary);
        $this->assertSame('https://canonical.example.com/first', $story->canonical_url);
        $this->assertSame('2026-09-24 12:00:00', $story->occurred_at->format('Y-m-d H:i:s'));
    }

    private function makeFeed(): SourceFeed
    {
        $source = Source::create([
            'name' => 'Example Source',
            'slug' => 'example-source',
        ]);

        return SourceFeed::create([
            'source_id' => $source->id,
            'name' => 'Example Feed',
            'url' => 'https://example.com/feed.xml',
        ]);
    }

    private function rss(): string
    {
        return $this->rssItems(<<<'XML'
        <item>
            <title>First story</title>
            <description>A short summary.</description>
            <link>https://example.com/stories/first</link>
            <guid>story-1</guid>
            <pubDate>Fri, 25 Sep 2026 12:00:00 +0000</pubDate>
        </item>
        XML);
    }

    private function rssItems(string $items): string
    {
        return <<<XML
<?xml version="1.0"?>
<rss version="2.0">
    <channel>
        <title>Example Feed</title>
{$items}
    </channel>
</rss>
XML;
    }

    private function atom(): string
    {
        return <<<'XML'
<?xml version="1.0"?>
<feed xmlns="http://www.w3.org/2005/Atom">
    <title>Example Atom Feed</title>
    <entry>
        <title>Atom story</title>
        <id>atom-1</id>
        <link href="https://example.com/atom-story" rel="alternate"/>
        <updated>2026-09-25T13:00:00+00:00</updated>
        <summary>Atom summary.</summary>
    </entry>
</feed>
XML;
    }
}
