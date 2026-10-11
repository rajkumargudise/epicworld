<?php

namespace App\Services\NewsWire;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Knows which 24/7 news channels are streaming - and allow embedding - right now.
 *
 * The old players pointed at YouTube's "live_stream?channel=" embed, which shows
 * "Something went wrong" whenever a channel is off air or has embedding switched off.
 * This resolves each channel's current live video id (refreshed every few minutes by
 * the scheduler, never during a page view), embeds that exact video, and quietly
 * leaves a channel out while it can't be played.
 */
class LiveChannelResolver
{
    public const CACHE_KEY = 'newswire:live_channels';

    public function __construct(private readonly YoutubeScraper $youtube) {}

    /**
     * @return array<string, string|null> channel id => live video id (null when off air / not embeddable)
     */
    public function refresh(): array
    {
        $channels = config('newswire.live_channels', []);

        $responses = Http::pool(function (Pool $pool) use ($channels) {
            foreach ($channels as $channel) {
                $pool->as($channel['channel_id'])->timeout(20)->withHeaders(YoutubeScraper::headers())
                    ->get('https://www.youtube.com/channel/'.$channel['channel_id'].'/live');
            }
        });

        $reachable = 0;
        $ids = [];

        foreach ($channels as $channel) {
            $response = $responses[$channel['channel_id']] ?? null;
            $ok = $response instanceof Response && $response->successful();
            $reachable += $ok ? 1 : 0;
            $ids[$channel['channel_id']] = $ok ? $this->youtube->liveVideoId($response->body()) : null;
        }

        // YouTube unreachable altogether: keep the last good answer instead of hiding every player.
        if ($reachable === 0 && is_array($previous = Cache::get(self::CACHE_KEY))) {
            return $previous;
        }

        $embeddable = $this->youtube->embeddable(array_filter($ids));

        foreach ($ids as $channelId => $videoId) {
            if ($videoId !== null && ! ($embeddable[$videoId] ?? true)) {
                $ids[$channelId] = null;
            }
        }

        Cache::put(self::CACHE_KEY, $ids, now()->addMinutes(30));

        return $ids;
    }

    /**
     * The channels worth showing, each with its live 'video_id' attached. Until the first
     * refresh has run, every channel is returned and falls back to the channel embed.
     *
     * @param  array<int, array<string, mixed>>  $channels
     * @return Collection<int, array<string, mixed>>
     */
    public function usable(array $channels, ?int $limit = null): Collection
    {
        $map = Cache::get(self::CACHE_KEY);

        $list = collect($channels)->map(function (array $channel) use ($map) {
            $channel['video_id'] = is_array($map) ? ($map[$channel['channel_id']] ?? null) : null;
            $channel['unknown'] = ! is_array($map);

            return $channel;
        })->filter(fn (array $channel) => $channel['unknown'] || $channel['video_id'] !== null)->values();

        if ($limit !== null) {
            // Featured channels first, then the configured order.
            $list = $list->sortBy(fn (array $channel) => empty($channel['featured']) ? 1 : 0)->values()->take($limit);
        }

        return $list;
    }
}
