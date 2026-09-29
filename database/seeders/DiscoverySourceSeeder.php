<?php

namespace Database\Seeders;

use App\Models\Source;
use App\Models\SourceFeed;
use App\Models\Topic;
use Illuminate\Database\Seeder;

/**
 * The minimum source/feed configuration needed to prove the real,
 * production discovery workflow (Milestone 15) end-to-end - not a
 * real-world feed catalog. Two feeds, chosen deliberately:
 *
 *   - NASA's official press-release feed: a stable, editorially
 *     unambiguous government source with no anti-bot or paywall
 *     behavior, good for proving single-source ingestion cleanly.
 *   - Hacker News' front page (via hnrss.org, an open, widely used
 *     RSS mirror of HN's own front page - not an arbitrary blog): a
 *     second, independent source with a different cadence, to prove
 *     multi-source discovery and per-feed failure isolation actually
 *     work against real, live infrastructure, not only fixtures.
 *
 * Both are read-only public feeds that require no credentials. Trust
 * and activation are set explicitly on every row rather than left to
 * a model default, per this milestone's "keep source trust/editor
 * configuration explicit" requirement - an editor reviewing sources
 * later sees a deliberate choice, not a default nobody made.
 */
class DiscoverySourceSeeder extends Seeder
{
    public function run(): void
    {
        $spaceTopic = Topic::where('slug', 'space')->first();

        $nasa = Source::updateOrCreate(
            ['slug' => 'nasa'],
            [
                'name' => 'NASA',
                'domain' => 'nasa.gov',
                'description' => "NASA's official news and press releases.",
                'source_type' => 'government',
                'homepage_url' => 'https://www.nasa.gov',
                'is_trusted' => true,
                'is_active' => true,
                'default_topic_id' => $spaceTopic?->id,
            ]
        );

        SourceFeed::updateOrCreate(
            ['source_id' => $nasa->id, 'url' => 'https://www.nasa.gov/news-release/feed/'],
            [
                'name' => 'NASA News Releases',
                'feed_type' => 'rss',
                'language' => 'en',
                'poll_interval_minutes' => 30,
                'is_active' => true,
            ]
        );

        $hackerNews = Source::updateOrCreate(
            ['slug' => 'hacker-news'],
            [
                'name' => 'Hacker News',
                'domain' => 'news.ycombinator.com',
                'description' => 'Community-ranked technology and startup discussion front page.',
                'source_type' => 'aggregator',
                'homepage_url' => 'https://news.ycombinator.com',
                // Community-ranked, not editorially produced - a real,
                // legitimate discovery signal, but explicitly not held
                // to the same trust level as a named publisher.
                'is_trusted' => false,
                'is_active' => true,
                'default_topic_id' => null,
            ]
        );

        SourceFeed::updateOrCreate(
            ['source_id' => $hackerNews->id, 'url' => 'https://hnrss.org/frontpage'],
            [
                'name' => 'Hacker News Front Page',
                'feed_type' => 'rss',
                'language' => 'en',
                'poll_interval_minutes' => 15,
                'is_active' => true,
            ]
        );
    }
}
