<?php

namespace App\Support;

/**
 * Single source of truth for canonical URL normalization and hashing.
 *
 * Both StoryMatcher (lookup) and Story (storage) must agree on exactly
 * the same normalization rules, otherwise a story could be stored under
 * one hash and searched for under another.
 */
class CanonicalUrl
{
    public static function normalize(?string $url): ?string
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

    /**
     * Deterministic, indexable identity for a canonical URL. Returns null
     * when there is nothing to hash so stories without a canonical URL
     * don't collide with one another under a unique index.
     */
    public static function hash(?string $url): ?string
    {
        $normalized = self::normalize($url);

        return $normalized !== null ? hash('sha256', $normalized) : null;
    }
}
