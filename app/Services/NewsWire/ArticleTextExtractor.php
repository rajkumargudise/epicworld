<?php

namespace App\Services\NewsWire;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fetches a published news page and returns its plain article text, purely as
 * private reference material for the report writer. The text is never stored or
 * shown to readers - only the writer's own, original wording is published.
 *
 * Fails soft: any network problem, paywall or unreadable page returns null and the
 * writer falls back to the headline, summary and what other outlets reported.
 */
class ArticleTextExtractor
{
    private const MAX_CHARS = 14000;

    public function fetch(string $url): ?string
    {
        if (! preg_match('#^https?://#i', $url)) {
            return null;
        }

        try {
            $response = Http::timeout(12)
                ->withHeaders(YoutubeScraper::headers() + ['Accept' => 'text/html,application/xhtml+xml'])
                ->get($url);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful() || ! str_contains((string) $response->header('Content-Type'), 'html')) {
            return null;
        }

        return $this->extract($response->body());
    }

    public function extract(string $html): ?string
    {
        // 1. Most news sites describe the article in JSON-LD, body included.
        if (preg_match_all('#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $blocks)) {
            foreach ($blocks[1] as $json) {
                $body = $this->articleBody(json_decode(trim($json), true));

                if ($body !== null && mb_strlen($body) > 500) {
                    return Str::limit($body, self::MAX_CHARS, '');
                }
            }
        }

        // 2. Otherwise the paragraphs of the <article> (or the whole page).
        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument;
        $loaded = @$doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return null;
        }

        $xpath = new DOMXPath($doc);

        foreach ($xpath->query('//script|//style|//nav|//header|//footer|//aside|//form|//noscript|//figure') ?: [] as $node) {
            $node->parentNode?->removeChild($node);
        }

        $scope = $xpath->query('//article')->item(0) ?? $xpath->query('//main')->item(0) ?? $doc->documentElement;
        $paragraphs = [];

        foreach ($xpath->query('.//p', $scope) ?: [] as $p) {
            $text = trim(preg_replace('/\s+/u', ' ', $p->textContent) ?? '');

            if (mb_strlen($text) >= 70 && ! preg_match('/^(advertisement|also read|read more|follow us|subscribe|copyright|©)/i', $text)) {
                $paragraphs[] = $text;
            }
        }

        $text = implode("\n\n", array_unique($paragraphs));

        return mb_strlen($text) > 500 ? Str::limit($text, self::MAX_CHARS, '') : null;
    }

    private function articleBody(mixed $data): ?string
    {
        if (! is_array($data)) {
            return null;
        }

        if (isset($data['articleBody']) && is_string($data['articleBody'])) {
            return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($data['articleBody']), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        }

        foreach ($data as $value) {
            if (is_array($value) && ($found = $this->articleBody($value)) !== null) {
                return $found;
            }
        }

        return null;
    }
}
