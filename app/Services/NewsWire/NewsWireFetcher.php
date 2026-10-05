<?php

namespace App\Services\NewsWire;

use App\Models\WireItem;
use Carbon\Carbon;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SimpleXMLElement;
use Throwable;

/**
 * Pulls every configured feed in parallel and stores each headline as a
 * WireItem, deduplicated by URL. Feeds are listed in config/newswire.php
 * (trusted, operator-chosen URLs - never user input). One failing feed
 * never affects the others, and everything stored is plain text: tags are
 * stripped from titles/summaries and only http(s) URLs are kept, so
 * nothing from a feed can inject markup.
 */
class NewsWireFetcher
{
    public const LAST_RUN_KEY = 'newswire:last_run';

    public const LOCK_KEY = 'newswire:lock';

    private const MEDIA_NS = 'http://search.yahoo.com/mrss/';

    private const YT_NS = 'http://www.youtube.com/xml/schemas/2015';

    /**
     * @return array{feeds: int, failed: int, new_items: int, pruned: int}
     */
    public function run(): array
    {
        $feeds = array_values(config('newswire.feeds', []));
        $stats = ['feeds' => count($feeds), 'failed' => 0, 'new_items' => 0, 'pruned' => 0];

        $responses = Http::pool(function (Pool $pool) use ($feeds) {
            foreach ($feeds as $i => $feed) {
                $pool->as((string) $i)
                    ->timeout(15)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; EpicWorldBot/1.0; +https://epicworld.in)'])
                    ->get($feed['url']);
            }
        });

        foreach ($feeds as $i => $feed) {
            $response = $responses[(string) $i] ?? null;

            if (! $response instanceof Response || ! $response->successful()) {
                $stats['failed']++;

                continue;
            }

            try {
                $stats['new_items'] += $this->store($feed, $this->parse($feed, $response->body()));
            } catch (Throwable $e) {
                $stats['failed']++;
                Log::warning('Newswire feed failed: '.$feed['url'].' - '.$e->getMessage());
            }
        }

        $stats['pruned'] = WireItem::query()
            ->where('published_at', '<', now()->subDays((int) config('newswire.retention_days', 5)))
            ->delete();

        Cache::put(self::LAST_RUN_KEY, now()->timestamp, now()->addDay());

        return $stats;
    }

    public function isStale(): bool
    {
        $last = Cache::get(self::LAST_RUN_KEY);

        return $last === null || (now()->timestamp - (int) $last) > config('newswire.stale_after_minutes', 5) * 60;
    }

    /**
     * @param  array<string, mixed>  $feed
     * @return array<int, array<string, mixed>>
     */
    public function parse(array $feed, string $body): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            throw new \RuntimeException('Unparseable feed');
        }

        $entries = isset($xml->channel->item) ? $xml->channel->item : ($xml->entry ?? []);
        $isVideoFeed = ($feed['kind'] ?? 'article') === 'video';
        $items = [];

        foreach ($entries as $entry) {
            if (count($items) >= (int) config('newswire.max_items_per_feed', 25)) {
                break;
            }

            $title = $this->text((string) $entry->title);
            $url = $this->link($entry);

            if ($title === '' || $url === null) {
                continue;
            }

            $media = $entry->children(self::MEDIA_NS);
            $videoId = null;

            if ($isVideoFeed) {
                $videoId = (string) ($entry->children(self::YT_NS)->videoId ?? '');

                if (! preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId)) {
                    continue;
                }
            }

            $rawSummary = (string) ($entry->description ?? $entry->summary ?? $media->group->description ?? $media->description ?? '');
            $encoded = (string) ($entry->children('http://purl.org/rss/1.0/modules/content/')->encoded ?? '');

            $items[] = [
                'kind' => $isVideoFeed ? 'video' : 'article',
                'scope' => $feed['scope'],
                'source' => $feed['source'],
                'title' => Str::limit($title, 300, '…'),
                'summary' => $this->summary($rawSummary),
                'url' => $url,
                'image_url' => $this->image($entry, $media, $rawSummary.' '.$encoded),
                'video_id' => $videoId,
                'published_at' => $this->date($entry),
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $feed
     * @param  array<int, array<string, mixed>>  $items
     */
    private function store(array $feed, array $items): int
    {
        $new = 0;

        foreach ($items as $item) {
            $hash = hash('sha256', $item['url']);

            $item['url_hash'] = $hash;

            $existing = WireItem::query()->where('url_hash', $hash)->first();

            if ($existing) {
                // Keep a fresher image/summary if the feed now has one.
                $existing->fill(array_filter([
                    'image_url' => $existing->image_url ?: $item['image_url'],
                    'summary' => $existing->summary ?: $item['summary'],
                ]))->save();

                continue;
            }

            WireItem::create($item);
            $new++;
        }

        return $new;
    }

    private function link(SimpleXMLElement $entry): ?string
    {
        $candidates = [];

        if (isset($entry->link)) {
            foreach ($entry->link as $link) {
                $href = (string) ($link['href'] ?? '');
                $rel = (string) ($link['rel'] ?? 'alternate');
                $candidates[] = $href !== '' ? ($rel === 'alternate' || $rel === '' ? $href : null) : (string) $link;
            }
        }

        $candidates[] = (string) ($entry->guid ?? '');

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);

            if ($candidate !== '' && preg_match('#^https?://#i', $candidate) && filter_var($candidate, FILTER_VALIDATE_URL)) {
                return mb_substr($candidate, 0, 2000);
            }
        }

        return null;
    }

    private function image(SimpleXMLElement $entry, SimpleXMLElement $media, string $html): ?string
    {
        $urls = [];

        foreach ([$media->thumbnail, $media->content, $media->group->thumbnail, $media->group->content] as $node) {
            if (isset($node[0])) {
                $attributes = $node[0]->attributes();

                if (isset($attributes['url'])) {
                    $urls[] = (string) $attributes['url'];
                }
            }
        }

        if (isset($entry->enclosure['url']) && str_starts_with((string) ($entry->enclosure['type'] ?? 'image'), 'image')) {
            $urls[] = (string) $entry->enclosure['url'];
        }

        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
            $urls[] = html_entity_decode($m[1]);
        }

        foreach ($urls as $url) {
            $url = trim($url);

            if (preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL)) {
                return mb_substr($url, 0, 2000);
            }
        }

        return null;
    }

    private function date(SimpleXMLElement $entry): Carbon
    {
        $raw = (string) ($entry->pubDate ?? $entry->published ?? $entry->updated ?? $entry->children('http://purl.org/dc/elements/1.1/')->date ?? '');

        try {
            $date = $raw !== '' ? Carbon::parse($raw) : now();
        } catch (Throwable) {
            $date = now();
        }

        // A feed claiming a time in the future is clamped to now.
        return $date->isFuture() ? now() : $date;
    }

    private function summary(string $html): ?string
    {
        $text = $this->text($html);

        return $text === '' ? null : Str::limit($text, 420, '…');
    }

    private function text(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }
}
