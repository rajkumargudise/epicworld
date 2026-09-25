<?php

namespace App\Enums;

enum StoryStatus: string
{
    case Discovered = 'discovered';
    case Candidate = 'candidate';
    case Processing = 'processing';
    case Review = 'review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Published = 'published';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Discovered => 'Discovered',
            self::Candidate => 'Candidate',
            self::Processing => 'Processing',
            self::Review => 'In Review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Published => 'Published',
            self::Failed => 'Failed',
        };
    }
}
