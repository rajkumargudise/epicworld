<?php

namespace App\Services\Feeds;

use App\Enums\StoryStatus;
use App\Models\SourceFeed;
use App\Models\Story;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use SimpleXMLElement;

class RssFeedIngestor
{
    public function ingest(SourceFeed $sourceFeed): Collection
    {
        try {
            $this->validateUrl($sourceFeed->url);

            $response = Http::timeout(10)
                ->accept('application/rss+xml, application/atom+xml, application/xml, text/xml')
                ->get($sourceFeed->url);

            if ($response->failed()) {
                throw new RuntimeException('Feed request returned HTTP '.$response->status().'.');
            }

            $body = $response->body();

            if (strlen($body) > 5_000_000) {
                throw new RuntimeException('Feed response exceeds the 5 MB limit.');
            }

            $items = $this->parse($body);
            $stories = collect();

            foreach ($items as $item) {
                $observedAt = now();
                $story = Story::firstOrNew(['content_hash' => $item['content_hash']]);

                if (! $story->exists) {
                    $story->fill([
                        'title' => $item['title'],
                        'slug' => $item['slug'],
                        'summary' => $item['summary'],
                        'canonical_url' => $item['source_url'],
                        'status' => StoryStatus::Discovered,
                        'occurred_at' => $item['published_at'],
                        'first_seen_at' => $item['published_at'] ?? $observedAt,
                    ]);
                } else {
                    $story->fill([
                        'title' => $item['title'],
                        'last_seen_at' => $observedAt,
                    ]);

                    if ($item['summary'] !== null) {
                        $story->summary = $item['summary'];
                    }

                    if ($item['source_url'] !== null) {
                        $story->canonical_url = $item['source_url'];
                    }

                    if ($item['published_at'] !== null) {
                        $story->occurred_at = $item['published_at'];
                    }
                }

                $story->last_seen_at = $observedAt;
                $story->save();

                $sourceFeed->source->stories()->syncWithoutDetaching([
                    $story->id => [
                        'source_url' => $item['source_url'],
                        'external_id' => $item['external_id'],
                        'title' => $item['title'],
                        'summary' => $item['summary'],
                        'published_at' => $item['published_at'],
                        'discovered_at' => now(),
                    ],
                ]);

                $stories->push($story);
            }

            $sourceFeed->forceFill([
                'last_fetched_at' => now(),
                'last_success_at' => now(),
                'last_failure_at' => null,
                'last_error' => null,
            ])->save();

            return $stories;
        } catch (RequestException|InvalidArgumentException|RuntimeException $exception) {
            $sourceFeed->forceFill([
                'last_fetched_at' => now(),
                'last_failure_at' => now(),
                'last_error' => Str::limit($exception->getMessage(), 1000, ''),
            ])->save();

            return collect();
        }
    }

    /**
     * @return list<array{
     *     title: string,
     *     slug: string,
     *     summary: ?string,
     *     source_url: ?string,
     *     external_id: ?string,
     *     published_at: ?Carbon,
     *     content_hash: string
     * }>
     */
    private function parse(string $body): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            throw new InvalidArgumentException('Feed content is not valid XML.');
        }

        if (isset($xml->channel->item)) {
            $items = [];

            foreach ($xml->channel->item as $item) {
                $items[] = $this->normalizeItem(
                    title: (string) $item->title,
                    summary: (string) ($item->description ?: $item->children('content', true)->encoded),
                    sourceUrl: (string) $item->link,
                    externalId: (string) $item->guid,
                    publishedAt: (string) $item->pubDate
                );
            }

            return array_values(array_filter($items));
        }

        $defaultNamespace = $xml->getDocNamespaces()[''] ?? null;

        if ($defaultNamespace !== null) {
            $xml->registerXPathNamespace('atom', $defaultNamespace);
        }

        $entries = $defaultNamespace !== null
            ? ($xml->xpath('//atom:entry') ?: [])
            : (isset($xml->entry) ? $xml->entry : []);

        if ($entries === null || count($entries) === 0) {
            throw new InvalidArgumentException('Feed format is unsupported.');
        }

        $items = [];

        foreach ($entries as $entry) {
            $links = $entry->link;
            $sourceUrl = null;

            foreach ($links as $link) {
                if ((string) $link['rel'] === '' || (string) $link['rel'] === 'alternate') {
                    $sourceUrl = (string) ($link['href'] ?: $link);
                    break;
                }
            }

            $items[] = $this->normalizeItem(
                title: (string) $entry->title,
                summary: (string) ($entry->summary ?: $entry->content),
                sourceUrl: $sourceUrl,
                externalId: (string) ($entry->id ?: $entry->link['href']),
                publishedAt: (string) ($entry->published ?: $entry->updated)
            );
        }

        return array_values(array_filter($items));
    }

    /**
     * @return array{
     *     title: string,
     *     slug: string,
     *     summary: ?string,
     *     source_url: ?string,
     *     external_id: ?string,
     *     published_at: ?Carbon,
     *     content_hash: string
     * }|null
     */
    private function normalizeItem(
        string $title,
        string $summary,
        ?string $sourceUrl,
        ?string $externalId,
        string $publishedAt
    ): ?array {
        $title = trim($title);
        $summary = trim($summary) ?: null;
        $sourceUrl = trim((string) $sourceUrl) ?: null;
        $externalId = trim((string) $externalId) ?: null;

        if ($title === '') {
            return null;
        }

        $publishedAt = trim($publishedAt);
        $published = null;

        if ($publishedAt !== '') {
            try {
                $published = Carbon::parse($publishedAt);
            } catch (InvalidFormatException) {
                $published = null;
            }
        }
        $identity = $externalId ?? $sourceUrl ?? $title;
        $contentHash = hash('sha256', $identity);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.substr($contentHash, 0, 12),
            'summary' => $summary,
            'source_url' => $sourceUrl,
            'external_id' => $externalId,
            'published_at' => $published,
            'content_hash' => $contentHash,
        ];
    }

    private function validateUrl(string $url): void
    {
        $parsed = parse_url($url);

        if (! in_array($parsed['scheme'] ?? null, ['http', 'https'], true) || empty($parsed['host'])) {
            throw new InvalidArgumentException('Feed URL must use HTTP or HTTPS.');
        }
    }
}
