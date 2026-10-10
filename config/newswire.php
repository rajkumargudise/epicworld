<?php

/*
|--------------------------------------------------------------------------
| Live news wire
|--------------------------------------------------------------------------
|
| Real-time headlines and videos shown on the site the moment they are
| fetched - no AI step and no approval queue. Each item is a headline,
| a short summary and a credit link to its publisher; full original
| articles are produced separately by the editorial pipeline.
|
| Scopes: world (international), news (India national), local
| (Hyderabad & Telangana). Change the local feeds to retarget the
| "Local" tab to another city.
|
*/

return [

    'enabled' => (bool) env('NEWSWIRE_ENABLED', true),

    // A visitor request triggers a refresh (after the response is sent)
    // when the last one is older than this - a fallback for hosts where
    // the scheduler cron isn't running yet.
    'stale_after_minutes' => (int) env('NEWSWIRE_STALE_MINUTES', 2),

    // How often the scheduler pulls every feed (1-59 minutes).
    'fetch_every_minutes' => (int) env('NEWSWIRE_FETCH_MINUTES', 1),

    'retention_days' => (int) env('NEWSWIRE_RETENTION_DAYS', 5),

    'max_items_per_feed' => 25,

    // Optional: YouTube Data API key for the "latest videos" feeds. Can
    // also be set in Admin > Settings (which takes precedence).
    'youtube_api_key' => env('YOUTUBE_API_KEY'),

    'scopes' => [
        'news' => ['label' => 'India', 'blurb' => 'What is happening across India, as it happens.'],
        'world' => ['label' => 'World', 'blurb' => 'Breaking and developing stories from around the globe.'],
        'local' => ['label' => 'Local', 'blurb' => 'Hyderabad & Telangana, as it happens.'],
    ],

    'feeds' => [
        // World
        ['scope' => 'world', 'source' => 'BBC News', 'url' => 'https://feeds.bbci.co.uk/news/world/rss.xml'],
        ['scope' => 'world', 'source' => 'Al Jazeera', 'url' => 'https://www.aljazeera.com/xml/rss/all.xml'],
        ['scope' => 'world', 'source' => 'The Guardian', 'url' => 'https://www.theguardian.com/world/rss'],
        ['scope' => 'world', 'source' => 'DW', 'url' => 'https://rss.dw.com/xml/rss-en-all'],
        ['scope' => 'world', 'source' => 'France 24', 'url' => 'https://www.france24.com/en/rss'],
        ['scope' => 'world', 'source' => 'NPR', 'url' => 'https://feeds.npr.org/1001/rss.xml'],
        ['scope' => 'world', 'source' => 'Sky News', 'url' => 'https://feeds.skynews.com/feeds/rss/world.xml'],
        ['scope' => 'world', 'source' => 'The Hindu', 'url' => 'https://www.thehindu.com/news/international/feeder/default.rss'],

        // News (India)
        ['scope' => 'news', 'source' => 'The Hindu', 'url' => 'https://www.thehindu.com/news/national/feeder/default.rss'],
        ['scope' => 'news', 'source' => 'NDTV', 'url' => 'https://feeds.feedburner.com/ndtvnews-top-stories'],
        ['scope' => 'news', 'source' => 'Times of India', 'url' => 'https://timesofindia.indiatimes.com/rssfeeds/-2128936835.cms'],
        ['scope' => 'news', 'source' => 'Zee News', 'url' => 'https://zeenews.india.com/rss/india-national-news.xml'],
        ['scope' => 'news', 'source' => 'NDTV India', 'url' => 'https://feeds.feedburner.com/ndtvnews-india-news'],
        ['scope' => 'news', 'source' => 'Hindustan Times', 'url' => 'https://www.hindustantimes.com/feeds/rss/india-news/rssfeed.xml'],
        ['scope' => 'news', 'source' => 'India Today', 'url' => 'https://www.indiatoday.in/rss/1206514'],

        // Local (Hyderabad & Telangana)
        ['scope' => 'local', 'source' => 'The Hindu', 'url' => 'https://www.thehindu.com/news/cities/Hyderabad/feeder/default.rss'],
        ['scope' => 'local', 'source' => 'The Hindu', 'url' => 'https://www.thehindu.com/news/national/telangana/feeder/default.rss'],
        ['scope' => 'local', 'source' => 'Times of India', 'url' => 'https://timesofindia.indiatimes.com/rssfeeds/-2128816011.cms'],
        ['scope' => 'local', 'source' => 'Telangana Today', 'url' => 'https://telanganatoday.com/feed'],

        // Latest videos (YouTube channel feeds; best-effort - YouTube's feed
        // endpoint is occasionally unavailable, in which case this run simply
        // adds no videos and existing ones stay).
        ['scope' => 'world', 'source' => 'Al Jazeera English', 'kind' => 'video', 'url' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UCNye-wNBqNL5ZzHSJj3l8Bg'],
        ['scope' => 'world', 'source' => 'DW News', 'kind' => 'video', 'url' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UCknLrEdhRCp1aegoMqRaCZg'],
        ['scope' => 'world', 'source' => 'France 24', 'kind' => 'video', 'url' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UCQfwfsi5VrQ8yKZ-UWmAEFg'],
        ['scope' => 'world', 'source' => 'WION', 'kind' => 'video', 'url' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UC_gUM8rL-Lrg6O3adPW9K1g'],
        ['scope' => 'world', 'source' => 'CNA', 'kind' => 'video', 'url' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UC83jt4dlz1Gjl58fzQrrKZg'],
        ['scope' => 'news', 'source' => 'NDTV', 'kind' => 'video', 'url' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UCZFMm1mMw0F81Z37aaEzTUA'],
        ['scope' => 'news', 'source' => 'India Today', 'kind' => 'video', 'url' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UCYPvAwZP8pZhSMW8qs7cVCw'],
        ['scope' => 'local', 'source' => 'NTV Telugu', 'kind' => 'video', 'url' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UCumtYpCY26F6Jr3satUgMvA'],
        ['scope' => 'local', 'source' => 'Sakshi TV', 'kind' => 'video', 'url' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UCZ9m4KOh8Ei60428xeGYDCQ'],
    ],

    // 24/7 news channels embedded as live video (YouTube's own embed
    // player via the privacy-enhanced domain).
    'live_channels' => [
        ['name' => 'Al Jazeera English', 'channel_id' => 'UCNye-wNBqNL5ZzHSJj3l8Bg', 'scope' => 'world'],
        ['name' => 'DW News', 'channel_id' => 'UCknLrEdhRCp1aegoMqRaCZg', 'scope' => 'world'],
        ['name' => 'France 24 English', 'channel_id' => 'UCQfwfsi5VrQ8yKZ-UWmAEFg', 'scope' => 'world'],
        ['name' => 'Sky News', 'channel_id' => 'UCoMdktPbSTixAyNGwb-UYkQ', 'scope' => 'world'],
        ['name' => 'WION', 'channel_id' => 'UC_gUM8rL-Lrg6O3adPW9K1g', 'scope' => 'news'],
        ['name' => 'NDTV 24x7', 'channel_id' => 'UCZFMm1mMw0F81Z37aaEzTUA', 'scope' => 'news'],
        ['name' => 'India Today', 'channel_id' => 'UCYPvAwZP8pZhSMW8qs7cVCw', 'scope' => 'news'],
        ['name' => 'NTV Telugu', 'channel_id' => 'UCumtYpCY26F6Jr3satUgMvA', 'scope' => 'local'],
        ['name' => 'Sakshi TV', 'channel_id' => 'UCZ9m4KOh8Ei60428xeGYDCQ', 'scope' => 'local'],
    ],

];
