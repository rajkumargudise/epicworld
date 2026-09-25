<?php

namespace App\Services\Stories;

use App\Models\Source;
use App\Models\Story;

class StoryMatcher
{
    public function match(Source $source, ?string $canonicalUrl, ?string $externalId): StoryMatchResult
    {
        $normalizedUrl = $this->normalizeUrl($canonicalUrl);

        if ($normalizedUrl !== null) {
            $story = Story::query()
                ->whereNotNull('canonical_url')
                ->get()
                ->first(fn (Story $story): bool => $this->normalizeUrl($story->canonical_url) === $normalizedUrl);

            if ($story !== null) {
                return StoryMatchResult::exact($story, 'canonical_url');
            }
        }

        $externalId = $externalId !== null ? trim($externalId) : null;

        if ($externalId !== null && $externalId !== '') {
            $story = $source->stories()
                ->wherePivot('external_id', $externalId)
                ->first();

            if ($story !== null) {
                return StoryMatchResult::exact($story, 'source_external_id');
            }
        }

        return StoryMatchResult::noMatch();
    }

    public function normalizeUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $port = $parts['port'] ?? null;

        if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
            $port = null;
        }

        $normalized = $scheme.'://';

        if (isset($parts['user'])) {
            $normalized .= $parts['user'];

            if (isset($parts['pass'])) {
                $normalized .= ':'.$parts['pass'];
            }

            $normalized .= '@';
        }

        $normalized .= $host;

        if ($port !== null) {
            $normalized .= ':'.$port;
        }

        $normalized .= $parts['path'] ?? '/';

        if (isset($parts['query'])) {
            $normalized .= '?'.$parts['query'];
        }

        return $normalized;
    }
}
