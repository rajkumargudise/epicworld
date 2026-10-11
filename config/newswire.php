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

    // YouTube channel pages (latest videos) are re-read at most this often; headlines refresh every run.
    'videos_every_minutes' => (int) env('NEWSWIRE_VIDEO_MINUTES', 5),

    // Full in-site reports for live-desk headlines (written by the AI provider, in EpicWorld's
    // own words - see App\Services\NewsWire\WireReportWriter).
    'reports' => [
        'per_run' => (int) env('NEWSWIRE_REPORTS_PER_RUN', 1),
        'daily_cap' => (int) env('NEWSWIRE_REPORTS_DAILY_CAP', 200),
        'max_age_hours' => 48,
        'min_words' => 200,
        // Fraction of 8-word runs that may match the source verbatim before a draft is rejected.
        'max_overlap' => 0.12,
        // Written proactively; other scopes (world) are written when a reader opens the headline.
        'background_scopes' => ['news', 'local'],
    ],

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
        ['scope' => 'world', 'source' => 'The New York Times', 'url' => 'https://rss.nytimes.com/services/xml/rss/nyt/World.xml'],
        ['scope' => 'world', 'source' => 'CBS News', 'url' => 'https://www.cbsnews.com/latest/rss/world'],
        ['scope' => 'world', 'source' => 'ABC News', 'url' => 'https://abcnews.go.com/abcnews/internationalheadlines'],
        ['scope' => 'world', 'source' => 'Euronews', 'url' => 'https://www.euronews.com/rss?format=mrss'],
        ['scope' => 'world', 'source' => 'CNBC', 'url' => 'https://www.cnbc.com/id/100727362/device/rss/rss.html'],
        ['scope' => 'world', 'source' => 'The Independent', 'url' => 'https://www.independent.co.uk/news/world/rss'],
        ['scope' => 'world', 'source' => 'South China Morning Post', 'url' => 'https://www.scmp.com/rss/91/feed'],
        ['scope' => 'world', 'source' => 'The Straits Times', 'url' => 'https://www.straitstimes.com/news/world/rss.xml'],
        ['scope' => 'world', 'source' => 'CNA', 'url' => 'https://www.channelnewsasia.com/rssfeeds/8395986'],
        ['scope' => 'world', 'source' => 'ABC Australia', 'url' => 'https://www.abc.net.au/news/feed/2942460/rss.xml'],
        ['scope' => 'world', 'source' => 'The Japan Times', 'url' => 'https://www.japantimes.co.jp/feed/'],
        ['scope' => 'world', 'source' => 'Africanews', 'url' => 'https://www.africanews.com/feed/rss'],

        // News (India)
        ['scope' => 'news', 'source' => 'The Hindu', 'url' => 'https://www.thehindu.com/news/national/feeder/default.rss'],
        ['scope' => 'news', 'source' => 'NDTV', 'url' => 'https://feeds.feedburner.com/ndtvnews-top-stories'],
        ['scope' => 'news', 'source' => 'Times of India', 'url' => 'https://timesofindia.indiatimes.com/rssfeeds/-2128936835.cms'],
        ['scope' => 'news', 'source' => 'Zee News', 'url' => 'https://zeenews.india.com/rss/india-national-news.xml'],
        ['scope' => 'news', 'source' => 'NDTV India', 'url' => 'https://feeds.feedburner.com/ndtvnews-india-news'],
        ['scope' => 'news', 'source' => 'Hindustan Times', 'url' => 'https://www.hindustantimes.com/feeds/rss/india-news/rssfeed.xml'],
        ['scope' => 'news', 'source' => 'India Today', 'url' => 'https://www.indiatoday.in/rss/1206514'],
        ['scope' => 'news', 'source' => 'Mint', 'url' => 'https://www.livemint.com/rss/news'],
        ['scope' => 'news', 'source' => 'The Economic Times', 'url' => 'https://economictimes.indiatimes.com/rssfeedstopstories.cms'],
        ['scope' => 'news', 'source' => 'India TV', 'url' => 'https://www.indiatvnews.com/rssnews/topstory-india.xml'],
        ['scope' => 'news', 'source' => 'ABP Live', 'url' => 'https://news.abplive.com/news/india/feed'],
        ['scope' => 'news', 'source' => 'Scroll.in', 'url' => 'https://feeds.feedburner.com/ScrollinArticles.rss'],

        // Local (Hyderabad & Telangana)
        ['scope' => 'local', 'source' => 'The Hindu', 'url' => 'https://www.thehindu.com/news/cities/Hyderabad/feeder/default.rss'],
        ['scope' => 'local', 'source' => 'The Hindu', 'url' => 'https://www.thehindu.com/news/national/telangana/feeder/default.rss'],
        ['scope' => 'local', 'source' => 'Times of India', 'url' => 'https://timesofindia.indiatimes.com/rssfeeds/-2128816011.cms'],
        ['scope' => 'local', 'source' => 'Telangana Today', 'url' => 'https://telanganatoday.com/feed'],
        ['scope' => 'local', 'source' => 'The Siasat Daily', 'url' => 'https://www.siasat.com/feed/'],
        ['scope' => 'local', 'source' => 'Sakshi', 'url' => 'https://www.sakshi.com/rss.xml'],

        // Latest videos. YouTube retired its channel RSS feeds, so these are read from each
        // channel's public page (every few minutes) - only the channel_id in the URL is used.
        ['scope' => 'world', 'source' => 'Al Jazeera English', 'kind' => 'video', 'url' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UCNye-wNBqNL5ZzHSJj3l8Bg'],
        ['scope' => 'world', 'source' => 'DW News', 'kind' => 'video', 'url' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UCknLrEdhRCp1aegoMqRaCZg'],
        ['scope' => 'world', 'source' => 'France 24', 'kind' => 'video', 'url' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UCQfwfsi5VrQ8yKZ-UWmAEFg'],
        ['scope' => 'world', 'source' => 'WION', 'kind' => 'video', 'url' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UC_gUM8rL-Lrg6O3adPW9K1g'],
        ['scope' => 'world', 'source' => 'CNA', 'kind' => 'video', 'url' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UC83jt4dlz1Gjl58fzQrrKZg'],
        ['scope' => 'world', 'source' => 'Sky News', 'kind' => 'video', 'url' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UCoMdktPbSTixAyNGwb-UYkQ'],
        ['scope' => 'world', 'source' => 'Euronews', 'kind' => 'video', 'url' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UCSrZ3UV4jOidv8ppoVuvW9Q'],
        ['scope' => 'world', 'source' => 'TRT World', 'kind' => 'video', 'url' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UC7fWeaHhqgM4Ry-RMpM2YYw'],
        ['scope' => 'news', 'source' => 'NDTV', 'kind' => 'video', 'url' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UCZFMm1mMw0F81Z37aaEzTUA'],
        ['scope' => 'news', 'source' => 'India Today', 'kind' => 'video', 'url' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UCYPvAwZP8pZhSMW8qs7cVCw'],
        ['scope' => 'news', 'source' => 'Aaj Tak', 'kind' => 'video', 'url' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UCt4t-jeY85JegMlZ-E5UWtA'],
        ['scope' => 'news', 'source' => 'ABP News', 'kind' => 'video', 'url' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UCRWFSbif-RFENbBrSiez1DA'],
        ['scope' => 'news', 'source' => 'Republic World', 'kind' => 'video', 'url' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UCwqusr8YDwM-3mEYTDeJHzw'],
        ['scope' => 'news', 'source' => 'Times Now', 'kind' => 'video', 'url' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UC6RJ7-PaXg6TIH2BzZfTV7w'],
        ['scope' => 'news', 'source' => 'CNN-News18', 'kind' => 'video', 'url' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UCef1-8eOpJgud7szVPlZQAQ'],
        ['scope' => 'news', 'source' => 'Zee News', 'kind' => 'video', 'url' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UCIvaYmXn910QMdemBG3v1pQ'],
        ['scope' => 'local', 'source' => 'NTV Telugu', 'kind' => 'video', 'url' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UCumtYpCY26F6Jr3satUgMvA'],
        ['scope' => 'local', 'source' => 'Sakshi TV', 'kind' => 'video', 'url' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UCZ9m4KOh8Ei60428xeGYDCQ'],
        ['scope' => 'local', 'source' => 'ABN Telugu', 'kind' => 'video', 'url' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UC_2irx_BQR7RsBKmUV9fePQ'],
        ['scope' => 'local', 'source' => 'TV9 Telugu', 'kind' => 'video', 'url' => 'https://www.youtube.com/feeds/videos.xml?channel_id=UCPXTXMecYqnRKNdqdVOGSFg'],
    ],

    // 24/7 news channels, embedded as live video. Which of them is actually streaming (and
    // embeddable) is worked out every few minutes by LiveChannelResolver; an off-air channel is
    // simply left out until it returns. 'featured' channels lead the home page.
    'live_channels' => [
        ['name' => 'NDTV 24x7', 'channel_id' => 'UCZFMm1mMw0F81Z37aaEzTUA', 'scope' => 'news', 'featured' => true],
        ['name' => 'Aaj Tak', 'channel_id' => 'UCt4t-jeY85JegMlZ-E5UWtA', 'scope' => 'news', 'featured' => true],
        ['name' => 'Al Jazeera English', 'channel_id' => 'UCNye-wNBqNL5ZzHSJj3l8Bg', 'scope' => 'world', 'featured' => true],
        ['name' => 'NTV Telugu', 'channel_id' => 'UCumtYpCY26F6Jr3satUgMvA', 'scope' => 'local', 'featured' => true],
        ['name' => 'India Today', 'channel_id' => 'UCYPvAwZP8pZhSMW8qs7cVCw', 'scope' => 'news'],
        ['name' => 'ABP News', 'channel_id' => 'UCRWFSbif-RFENbBrSiez1DA', 'scope' => 'news'],
        ['name' => 'Republic World', 'channel_id' => 'UCwqusr8YDwM-3mEYTDeJHzw', 'scope' => 'news'],
        ['name' => 'Times Now', 'channel_id' => 'UC6RJ7-PaXg6TIH2BzZfTV7w', 'scope' => 'news'],
        ['name' => 'CNN-News18', 'channel_id' => 'UCef1-8eOpJgud7szVPlZQAQ', 'scope' => 'news'],
        ['name' => 'WION', 'channel_id' => 'UC_gUM8rL-Lrg6O3adPW9K1g', 'scope' => 'news'],
        ['name' => 'Zee News', 'channel_id' => 'UCIvaYmXn910QMdemBG3v1pQ', 'scope' => 'news'],
        ['name' => 'NDTV India', 'channel_id' => 'UC9CYT9gSNLevX5ey2_6CK0Q', 'scope' => 'news'],
        ['name' => 'TV9 Bharatvarsh', 'channel_id' => 'UCOutOIcn_oho8pyVN3Ng-Pg', 'scope' => 'news'],
        ['name' => 'India TV', 'channel_id' => 'UCttspZesZIDEwwpVIgoZtWQ', 'scope' => 'news'],
        ['name' => 'NewsX', 'channel_id' => 'UCytSP0M0Jdnw6qIy3Y-nTig', 'scope' => 'news'],
        ['name' => 'Firstpost', 'channel_id' => 'UCz8QaiQxApLq8sLNcszYyJw', 'scope' => 'news'],
        ['name' => 'Mirror Now', 'channel_id' => 'UCWCEYVwSqr7Epo6sSCfUgiw', 'scope' => 'news'],
        ['name' => 'DW News', 'channel_id' => 'UCknLrEdhRCp1aegoMqRaCZg', 'scope' => 'world'],
        ['name' => 'France 24 English', 'channel_id' => 'UCQfwfsi5VrQ8yKZ-UWmAEFg', 'scope' => 'world'],
        ['name' => 'Sky News', 'channel_id' => 'UCoMdktPbSTixAyNGwb-UYkQ', 'scope' => 'world'],
        ['name' => 'Euronews', 'channel_id' => 'UCSrZ3UV4jOidv8ppoVuvW9Q', 'scope' => 'world'],
        ['name' => 'Bloomberg TV', 'channel_id' => 'UCIALMKvObZNtJ6AmdCLP7Lg', 'scope' => 'world'],
        ['name' => 'TRT World', 'channel_id' => 'UC7fWeaHhqgM4Ry-RMpM2YYw', 'scope' => 'world'],
        ['name' => 'NHK World-Japan', 'channel_id' => 'UCSPEjw8F2nQDtmUKPFNF7_A', 'scope' => 'world'],
        ['name' => 'CGTN', 'channel_id' => 'UCgrNz-aDmcr2uuto8_DL2jg', 'scope' => 'world'],
        ['name' => 'ABC News Live', 'channel_id' => 'UCBi2mrWuNuyYy4gbM6fU18Q', 'scope' => 'world'],
        ['name' => 'NBC News NOW', 'channel_id' => 'UCeY0bbntWzzVIaj2z3QigXg', 'scope' => 'world'],
        ['name' => 'Africanews', 'channel_id' => 'UC1_E8NeF5QHY2dtdLRBCCLA', 'scope' => 'world'],
        ['name' => 'Sakshi TV', 'channel_id' => 'UCZ9m4KOh8Ei60428xeGYDCQ', 'scope' => 'local'],
        ['name' => 'ABN Telugu', 'channel_id' => 'UC_2irx_BQR7RsBKmUV9fePQ', 'scope' => 'local'],
        ['name' => 'V6 News Telugu', 'channel_id' => 'UCDCMjD1XIAsCZsYHNMGVcog', 'scope' => 'local'],
        ['name' => 'TV9 Telugu', 'channel_id' => 'UCPXTXMecYqnRKNdqdVOGSFg', 'scope' => 'local'],
        ['name' => 'TV5 News', 'channel_id' => 'UCAR3h_9fLV82N2FH4cE4RKw', 'scope' => 'local'],
        ['name' => '10TV News Telugu', 'channel_id' => 'UCfymZbh17_3T_UhgjkQ9fRQ', 'scope' => 'local'],
        ['name' => 'ETV Telangana', 'channel_id' => 'UC6ickpgDIsltU_-8CbZaksQ', 'scope' => 'local'],
    ],

];
