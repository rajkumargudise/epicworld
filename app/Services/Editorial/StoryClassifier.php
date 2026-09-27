<?php

namespace App\Services\Editorial;

use App\Models\Story;

/**
 * Assigns a Story's Topic deterministically, from editor-configured
 * signal only - never by guessing from the Story's text. This is
 * deliberately not an AI classifier: Milestone 5 builds the editorial
 * domain on deterministic, testable logic first, and AI-assisted
 * classification (if ever added) will be a distinct, explicitly
 * selected provider step reviewed like any other AI-assisted output.
 *
 * The only signal used today is Source::default_topic_id - a topic an
 * editor attached to a source when configuring it (e.g. "TechCrunch"
 * -> Technology). A Story can carry evidence from more than one
 * Source; when those sources disagree, the source whose observation
 * of this Story was discovered earliest wins (source_story.discovered_at
 * ascending, with source_id as a stable tiebreaker when discovered_at
 * is null or tied), since that is the source discovery treated as
 * authoritative for this Story first. A Story with no source that has
 * a default topic is left unclassified rather than guessed at.
 */
class StoryClassifier
{
    /**
     * Classify $story if it doesn't already have a topic. Never
     * overwrites an existing topic_id - that may be a manual editorial
     * decision, and this classifier has no basis to second-guess one.
     *
     * Returns true only when a topic was actually assigned.
     */
    public function classify(Story $story): bool
    {
        if ($story->topic_id !== null) {
            return false;
        }

        $topicId = $story->sources()
            ->whereNotNull('default_topic_id')
            ->orderByRaw('source_story.discovered_at is null')
            ->orderByPivot('discovered_at')
            ->orderByPivot('source_id')
            ->value('default_topic_id');

        if ($topicId === null) {
            return false;
        }

        $story->update(['topic_id' => $topicId]);

        return true;
    }
}
