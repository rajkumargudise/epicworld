<?php

namespace App\Services\Stories;

use App\Models\Source;
use App\Models\Story;
use App\Support\CanonicalUrl;

class StoryMatcher
{
    public function match(Source $source, ?string $canonicalUrl, ?string $externalId): StoryMatchResult
    {
        $hash = CanonicalUrl::hash($canonicalUrl);

        if ($hash !== null) {
            $story = Story::query()
                ->where('canonical_url_hash', $hash)
                ->first();

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
        return CanonicalUrl::normalize($url);
    }
}
