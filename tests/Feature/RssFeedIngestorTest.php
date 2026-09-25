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

    public function test_matching_canonical_url_reuses_existing_story(): void
    {
        $feed = $this->makeFeed();
        $story = Story::create([
            'title' => 'Editorial title',
            'slug' => 'editorial-title',
            'summary' => 'Editorial summary.',
            'canonical_url' => 'https://example.com/stories/first',
            'status' => StoryStatus::Candidate,
        ]);
        Http::fake([$feed->url => Http::response($this->rss(), 200)]);

        $stories = app(RssFeedIngestor::class)->ingest($feed);

        $this->assertCount(1, $stories);
        $this->assertSame($story->id, $stories->first()->id);
        $this->assertSame(1, Story::count());
    }

    public function test_matching_canonical_url_does_not_reset_status_or_editorial_data(): void
    {
        $feed = $this->makeFeed();
        $story = Story::create([
            'title' => 'Editorial title',
            'slug' => 'editorial-title',
            'summary' => 'Editorial summary.',
            'canonical_url' => 'https://example.com/stories/first',
            'status' => StoryStatus::Published,
        ]);
        Http::fake([$feed->url => Http::response($this->rssWithItem(
            title: 'Feed title',
            summary: 'Feed summary.',
            link: 'https://example.com/stories/first',
            guid: 'different-guid',
        ), 200)]);

        app(RssFeedIngestor::class)->ingest($feed);
        $story->refresh();

        $this->assertSame(StoryStatus::Published, $story->status);
        $this->assertSame('Editorial title', $story->title);
        $this->assertSame('Editorial summary.', $story->summary);
    }

    public function test_matching_source_external_id_reuses_existing_story(): void
    {
        $feed = $this->makeFeed();
        $story = Story::create([
            'title' => 'Existing story',
            'slug' => 'existing-story',
            'status' => StoryStatus::Review,
        ]);
        $feed->source->stories()->attach($story, ['external_id' => 'source-123']);
        Http::fake([$feed->url => Http::response($this->rssWithItem(
            title: 'Feed title',
            summary: 'Feed summary.',
            link: null,
            guid: 'source-123',
        ), 200)]);

        $stories = app(RssFeedIngestor::class)->ingest($feed);

        $this->assertSame($story->id, $stories->first()->id);
        $this->assertSame(1, Story::count());
        $this->assertSame(StoryStatus::Review, $story->refresh()->status);
    }

    public function test_matching_story_preserves_first_seen_and_updates_last_seen(): void
    {
        $feed = $this->makeFeed();
        $story = Story::create([
            'title' => 'Existing story',
            'slug' => 'existing-story',
            'canonical_url' => 'https://example.com/stories/first',
            'first_seen_at' => '2026-09-24 12:00:00',
            'last_seen_at' => '2026-09-24 12:00:00',
        ]);
        Http::fake([$feed->url => Http::response($this->rss(), 200)]);

        $this->travelTo('2026-09-25 13:00:00');
        app(RssFeedIngestor::class)->ingest($feed);
        $story->refresh();

        $this->assertSame('2026-09-24 12:00:00', $story->first_seen_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-25 13:00:00', $story->last_seen_at->format('Y-m-d H:i:s'));
    }

    public function test_same_external_id_from_a_different_source_does_not_use_a_source_scoped_hash_fallback(): void
    {
        $firstFeed = $this->makeFeed('first-source');
        $secondFeed = $this->makeFeed('second-source');
        $response = $this->rssWithItem(
            title: 'Shared external identity',
            summary: 'Source-specific observation.',
            link: null,
            guid: 'shared-id',
        );
        Http::fakeSequence()
            ->push($response, 200)
            ->push($response, 200);
        $ingestor = app(RssFeedIngestor::class);

        $ingestor->ingest($firstFeed);
        $stories = $ingestor->ingest($secondFeed);

        $this->assertCount(0, $stories);
        $this->assertSame(1, Story::count());
        $this->assertSame(1, $firstFeed->source->stories()->count());
        $this->assertSame(0, $secondFeed->source->stories()->count());
        $this->assertSame('Shared external identity', Story::firstOrFail()->title);
    }

    private function makeFeed(string $sourceSlug = 'example-source'): SourceFeed
    {
        $source = Source::create([
            'name' => $sourceSlug,
            'slug' => $sourceSlug,
        ]);

        return SourceFeed::create([
            'source_id' => $source->id,
            'name' => 'Example Feed',
            'url' => 'https://example.com/feed.xml',
        ]);
    }

    private function rss(): string
    {
        return $this->rssWithItem(
            title: 'First story',
            summary: 'A short summary.',
            link: 'https://example.com/stories/first',
            guid: 'story-1',
            publishedAt: 'Fri, 25 Sep 2026 12:00:00 +0000',
        );
    }

    private function rssWithItem(
        string $title,
        ?string $summary,
        ?string $link,
        ?string $guid,
        ?string $publishedAt = null,
    ): string {
        $summaryXml = $summary !== null ? '<description>'.$summary.'</description>' : '';
        $linkXml = $link !== null ? '<link>'.$link.'</link>' : '';
        $guidXml = $guid !== null ? '<guid>'.$guid.'</guid>' : '';
        $publishedXml = $publishedAt !== null ? '<pubDate>'.$publishedAt.'</pubDate>' : '';

        return $this->rssItems(<<<XML
        <item>
            <title>{$title}</title>
            {$summaryXml}
            {$linkXml}
            {$guidXml}
            {$publishedXml}
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
