<?php

namespace App\Services\NewsWire;

use Carbon\Carbon;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Reads what the site needs from YouTube without an API key.
 *
 * YouTube retired its public channel RSS feeds (they now return 404), so the latest
 * uploads are read from the channel's public /videos page and the current live stream
 * from its /live page. Both are parsed defensively: if YouTube changes its markup the
 * result is simply "nothing found" and the site keeps what it already has.
 */
class YoutubeScraper
{
    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

    /**
     * @return array<string, string>
     */
    public static function headers(): array
    {
        return ['User-Agent' => self::UA, 'Accept-Language' => 'en-US,en;q=0.9', 'Cookie' => 'CONSENT=YES+1; SOCS=CAI'];
    }

    /**
     * Newest uploads first.
     *
     * @return array<int, array{id: string, title: string, published_at: Carbon}>
     */
    public function videos(string $html, int $max = 10): array
    {
        if (! preg_match_all('/"videoRenderer":\{"videoId":"([\w-]{11})"/', $html, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $found = [];
        $offsets = array_column($matches[1], 1);

        foreach ($matches[1] as $n => [$id, $offset]) {
            if (isset($found[$id])) {
                continue;
            }

            // Each renderer's data ends where the next one starts.
            $end = $offsets[$n + 1] ?? ($offset + 7000);
            $chunk = substr($html, $offset, min(7000, $end - $offset));

            if (str_contains($chunk, '"upcomingEventData"')) {
                continue;
            }

            if (! preg_match('/"title":\{"runs":\[\{"text":"((?:[^"\\\\]|\\\\.)*)"/', $chunk, $title)) {
                continue;
            }

            $text = json_decode('"'.$title[1].'"');
            $text = trim(is_string($text) ? $text : $title[1]);

            if ($text === '') {
                continue;
            }

            $published = preg_match('/"publishedTimeText":\{"simpleText":"([^"]+)"/', $chunk, $when)
                ? $this->relative($when[1])
                : now();

            $found[$id] = ['id' => $id, 'title' => $text, 'published_at' => $published];

            if (count($found) >= $max) {
                break;
            }
        }

        return array_values($found);
    }

    /**
     * The video id of the stream a channel is broadcasting right now, or null.
     */
    public function liveVideoId(string $html): ?string
    {
        if (! preg_match('/"currentVideoEndpoint":.{0,400}?"videoId":"([\w-]{11})"/s', $html, $m)) {
            return null;
        }

        return str_contains($html, '"isLive":true') ? $m[1] : null;
    }

    /**
     * Which of these videos YouTube allows to be embedded (oEmbed answers 401 when the
     * owner disabled embedding, 404 when the video is gone). A timeout counts as "fine" so
     * a hiccup never hides good videos.
     *
     * @param  array<int, string>  $ids
     * @return array<string, bool>
     */
    public function embeddable(array $ids): array
    {
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return [];
        }

        $responses = Http::pool(function (Pool $pool) use ($ids) {
            foreach ($ids as $id) {
                $pool->as($id)->timeout(10)->withHeaders(self::headers())
                    ->get('https://www.youtube.com/oembed', ['format' => 'json', 'url' => 'https://www.youtube.com/watch?v='.$id]);
            }
        });

        $result = [];

        foreach ($ids as $id) {
            $response = $responses[$id] ?? null;
            $result[$id] = ! ($response instanceof Response) || ! in_array($response->status(), [401, 403, 404], true);
        }

        return $result;
    }

    private function relative(string $text): Carbon
    {
        if (preg_match('/(\d+)\s+(second|minute|hour|day|week|month|year)s?\s+ago/i', $text, $m)) {
            $unit = strtolower($m[2]);

            return now()->sub($unit, (int) $m[1]);
        }

        return now();
    }
}
