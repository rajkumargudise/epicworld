<?php

namespace App\Services\Feeds;

use App\Enums\StoryStatus;
use App\Models\SourceFeed;
use App\Models\Story;
use App\Services\Stories\StoryMatcher;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use SimpleXMLElement;

class RssFeedIngestor
{
    public function __construct(
        private readonly StoryMatcher $storyMatcher,
    ) {}

    public function ingest(SourceFeed $sourceFeed): Collection
    {
        // is_active defaults to true at the database level and is NOT NULL;
        // a freshly created model that didn't set it explicitly can still
        // read as null in memory until refreshed, so only an explicit
        // false should skip ingestion.
        if ($sourceFeed->is_active === false) {
            return collect();
        }

        try {
            $this->validateUrl($sourceFeed->url);

            // allow_redirects disabled deliberately: validateUrl() above
            // only checks the URL we were given. Laravel's HTTP client
            // follows redirects by default, and a feed URL that passes
            // validation could still redirect to a private/internal
            // address at request time - the exact SSRF this guard
            // exists to close. A feed that genuinely redirects fails
            // with a clear 3xx error below rather than being silently
            // followed anywhere.
            $response = Http::timeout(10)
                ->withOptions(['allow_redirects' => false])
                ->accept('application/rss+xml, application/atom+xml, application/xml, text/xml')
                ->get($sourceFeed->url);

            if ($response->redirect()) {
                throw new RuntimeException('Feed URL redirected (HTTP '.$response->status().'); redirects are not followed for SSRF safety.');
            }

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
                $match = $this->storyMatcher->match(
                    $sourceFeed->source,
                    $item['source_url'],
                    $item['external_id'],
                );
                $story = $match->story;

                if ($story === null) {
                    $story = new Story([
                        'content_hash' => $item['content_hash'],
                        'title' => $item['title'],
                        'slug' => $item['slug'],
                        'summary' => $item['summary'],
                        'canonical_url' => $item['source_url'],
                        'status' => StoryStatus::Discovered,
                        'occurred_at' => $item['published_at'],
                        'first_seen_at' => $item['published_at'] ?? $observedAt,
                    ]);

                    $story->last_seen_at = $observedAt;

                    try {
                        $story->save();
                    } catch (QueryException $exception) {
                        $isDuplicateIdentity = str_contains($exception->getMessage(), 'content_hash')
                            || str_contains($exception->getMessage(), 'canonical_url_hash');

                        if (! $isDuplicateIdentity) {
                            throw $exception;
                        }

                        continue;
                    }
                } elseif ($match->matched) {
                    $story->last_seen_at = $observedAt;
                    $story->save();
                }

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

        // external_id is a varchar(191) column (Schema::defaultStringLength); some publishers use
        // very long article URLs as their GUID. A deterministic digest
        // keeps the id stable across runs without overflowing it.
        if ($externalId !== null && mb_strlen($externalId) > 191) {
            $externalId = 'sha256:'.hash('sha256', $externalId);
        }

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
        $host = $parsed['host'] ?? null;

        if (! in_array($parsed['scheme'] ?? null, ['http', 'https'], true) || empty($host)) {
            throw new InvalidArgumentException('Feed URL must use HTTP or HTTPS.');
        }

        if ($this->isInternalHost($host)) {
            throw new InvalidArgumentException('Feed URL must not target a private, loopback, or internal address.');
        }
    }

    /**
     * A minimal, network-free SSRF guard: rejects a feed URL that points
     * at an obviously internal target - a loopback/private/link-local
     * literal IP (including the cloud metadata address,
     * 169.254.169.254) or a well-known internal hostname - so a
     * misconfigured or malicious feed URL can never turn a scheduled
     * discovery run into a way to reach this server's own network.
     *
     * Deliberately does not resolve DNS for ordinary hostnames: that
     * would add a real network call to every ingest (undesirable in
     * tests, and slow in production) for a feed list that is
     * operator-configured, not arbitrary public input - and it would
     * open its own DNS-rebinding TOCTOU gap between the check and the
     * actual request. Literal internal addresses are the concrete,
     * checkable threat this guards against.
     */
    private function isInternalHost(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));

        if (in_array($host, ['localhost', '0.0.0.0'], true)) {
            return true;
        }

        foreach (['.local', '.internal', '.localdomain'] as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        }

        return false;
    }
}
