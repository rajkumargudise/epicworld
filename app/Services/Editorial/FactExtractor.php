<?php

namespace App\Services\Editorial;

use App\Models\Story;
use Illuminate\Support\Carbon;

/**
 * Builds the Story's ground-truth fact sheet from what its sources
 * have actually reported - never from inference or AI. This is
 * deliberately not NLP-style extraction from free text: every fact
 * this produces is a field a source already gave us verbatim (its
 * reported title, summary, publish time and URL), tagged with which
 * source said it and when. That fact sheet is what a later,
 * AI-assisted drafting stage (Milestone 6+) will be required to stay
 * inside of - it is the provenance record that makes "no fabricated
 * facts" checkable rather than just a policy.
 */
class FactExtractor
{
    /**
     * Rebuild $story->facts from its current source observations.
     * Idempotent and non-destructive to anything else stored in
     * facts: re-running with the same source data produces the same
     * list, and a fact whose source data changes (e.g. a corrected
     * headline) is replaced, not duplicated, keyed by source_id.
     *
     * Returns true when the stored facts actually changed.
     */
    public function extract(Story $story): bool
    {
        $observations = $story->sources()
            ->orderByPivot('discovered_at')
            ->get();

        $facts = [];

        foreach ($observations as $source) {
            $pivot = $source->pivot;

            // The pivot model isn't cast, so published_at may arrive as
            // a raw datetime string rather than a Carbon instance.
            $publishedAt = $pivot->published_at !== null
                ? Carbon::parse($pivot->published_at)->toIso8601String()
                : null;

            $reported = array_filter([
                'title' => $pivot->title,
                'summary' => $pivot->summary,
                'published_at' => $publishedAt,
                'source_url' => $pivot->source_url,
            ], fn ($value) => $value !== null && $value !== '');

            if ($reported === []) {
                continue;
            }

            $facts[(string) $source->id] = [
                'source_id' => $source->id,
                'source_name' => $source->name,
                'reported' => $reported,
            ];
        }

        // array_values: facts is a list for storage, the keying above
        // is only to de-duplicate/replace by source_id while building.
        $facts = array_values($facts);

        if ($facts === ($story->facts ?? [])) {
            return false;
        }

        $story->update(['facts' => $facts]);

        return true;
    }
}
