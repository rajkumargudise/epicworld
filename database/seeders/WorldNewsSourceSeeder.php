<?php

namespace Database\Seeders;

use App\Models\Source;
use App\Models\SourceFeed;
use App\Models\Topic;
use Illuminate\Database\Seeder;

/**
 * A broad set of public news feeds from around the world, used as the
 * discovery signal for EPIC World's own reporting. Feeds supply the
 * facts (headline, summary, link); the editorial pipeline extracts a
 * fact sheet and the AI writes an ORIGINAL article from those facts,
 * credits the source, and a human approves it before anything is
 * published. Nothing here republishes a publisher's article text.
 *
 * Idempotent (updateOrCreate) - safe to re-run. Each feed can be
 * paused later from the database without touching code.
 */
class WorldNewsSourceSeeder extends Seeder
{
    /**
     * [source slug, source name, domain, homepage, type, topic slug, feed name, feed url, poll minutes]
     *
     * @var array<int, array<int, string|int>>
     */
    private const FEEDS = [
        // World
        ['bbc-news-world', 'BBC News', 'bbc.co.uk', 'https://www.bbc.co.uk/news', 'publisher', 'global-affairs', 'BBC World', 'https://feeds.bbci.co.uk/news/world/rss.xml', 20],
        ['al-jazeera', 'Al Jazeera', 'aljazeera.com', 'https://www.aljazeera.com', 'publisher', 'global-affairs', 'Al Jazeera', 'https://www.aljazeera.com/xml/rss/all.xml', 20],
        ['the-guardian', 'The Guardian', 'theguardian.com', 'https://www.theguardian.com', 'publisher', 'global-affairs', 'Guardian World', 'https://www.theguardian.com/world/rss', 20],
        ['deutsche-welle', 'DW', 'dw.com', 'https://www.dw.com', 'publisher', 'global-affairs', 'DW Top Stories', 'https://rss.dw.com/xml/rss-en-all', 30],
        ['france-24', 'France 24', 'france24.com', 'https://www.france24.com', 'publisher', 'global-affairs', 'France 24', 'https://www.france24.com/en/rss', 30],
        ['npr', 'NPR', 'npr.org', 'https://www.npr.org', 'publisher', 'global-affairs', 'NPR News', 'https://feeds.npr.org/1001/rss.xml', 30],

        // India
        ['the-hindu', 'The Hindu', 'thehindu.com', 'https://www.thehindu.com', 'publisher', 'society', 'The Hindu National', 'https://www.thehindu.com/news/national/feeder/default.rss', 20],
        ['ndtv', 'NDTV', 'ndtv.com', 'https://www.ndtv.com', 'publisher', 'society', 'NDTV Top Stories', 'https://feeds.feedburner.com/ndtvnews-top-stories', 20],
        ['times-of-india', 'The Times of India', 'timesofindia.indiatimes.com', 'https://timesofindia.indiatimes.com', 'publisher', 'society', 'TOI Top Stories', 'https://timesofindia.indiatimes.com/rssfeeds/-2128936835.cms', 20],

        // Technology & AI
        ['bbc-news-technology', 'BBC News', 'bbc.co.uk', 'https://www.bbc.co.uk/news', 'publisher', 'software', 'BBC Technology', 'https://feeds.bbci.co.uk/news/technology/rss.xml', 30],
        ['techcrunch', 'TechCrunch', 'techcrunch.com', 'https://techcrunch.com', 'publisher', 'software', 'TechCrunch', 'https://techcrunch.com/feed/', 20],
        ['the-verge', 'The Verge', 'theverge.com', 'https://www.theverge.com', 'publisher', 'gadgets', 'The Verge', 'https://www.theverge.com/rss/index.xml', 30],
        ['ars-technica', 'Ars Technica', 'arstechnica.com', 'https://arstechnica.com', 'publisher', 'software', 'Ars Technica', 'https://feeds.arstechnica.com/arstechnica/index', 30],

        // Cybersecurity
        ['krebs-on-security', 'Krebs on Security', 'krebsonsecurity.com', 'https://krebsonsecurity.com', 'publisher', 'threats', 'Krebs on Security', 'https://krebsonsecurity.com/feed/', 60],

        // Business & finance
        ['bbc-news-business', 'BBC News', 'bbc.co.uk', 'https://www.bbc.co.uk/news', 'publisher', 'markets', 'BBC Business', 'https://feeds.bbci.co.uk/news/business/rss.xml', 30],
        ['cnbc', 'CNBC', 'cnbc.com', 'https://www.cnbc.com', 'publisher', 'markets', 'CNBC Top News', 'https://www.cnbc.com/id/100003114/device/rss/rss.html', 20],

        // Science
        ['bbc-news-science', 'BBC News', 'bbc.co.uk', 'https://www.bbc.co.uk/news', 'publisher', 'research', 'BBC Science & Environment', 'https://feeds.bbci.co.uk/news/science_and_environment/rss.xml', 60],
        ['sciencedaily', 'ScienceDaily', 'sciencedaily.com', 'https://www.sciencedaily.com', 'publisher', 'research', 'ScienceDaily Top', 'https://www.sciencedaily.com/rss/top.xml', 60],
    ];

    public function run(): void
    {
        foreach (self::FEEDS as [$slug, $name, $domain, $homepage, $type, $topicSlug, $feedName, $feedUrl, $poll]) {
            $topic = Topic::where('slug', $topicSlug)->first();

            $source = Source::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'domain' => $domain,
                    'description' => "{$name} - public news feed used as a discovery source.",
                    'source_type' => $type,
                    'homepage_url' => $homepage,
                    'is_trusted' => true,
                    'is_active' => true,
                    'default_topic_id' => $topic?->id,
                ]
            );

            SourceFeed::updateOrCreate(
                ['source_id' => $source->id, 'url' => $feedUrl],
                [
                    'name' => $feedName,
                    'feed_type' => 'rss',
                    'language' => 'en',
                    'poll_interval_minutes' => $poll,
                    'is_active' => true,
                ]
            );
        }
    }
}
