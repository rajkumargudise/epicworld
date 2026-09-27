<?php

namespace App\Services\Stories;

use App\Enums\StoryStatus;
use App\Models\Story;
use Illuminate\Database\Eloquent\Builder;

class StoryQualificationService
{
    public function qualify(Story $story): bool
    {
        if (! $this->canQualify($story)) {
            return false;
        }

        $story->status = StoryStatus::Candidate;
        $story->save();

        return true;
    }

    public function canQualify(Story $story): bool
    {
        if ($story->status !== StoryStatus::Discovered) {
            return false;
        }

        if (trim($story->title) === '') {
            return false;
        }

        if (blank($story->content_hash) && blank($story->canonical_url)) {
            return false;
        }

        if ($story->sourceCount() === 0) {
            return false;
        }

        return $story->sources()
            ->where(function (Builder $query): void {
                $query->whereNotNull('source_story.source_url')
                    ->orWhereNotNull('source_story.external_id');
            })
            ->exists();
    }
}
