<?php

namespace App\Services\Stories;

use App\Models\Story;

readonly class StoryMatchResult
{
    public function __construct(
        public bool $matched,
        public ?Story $story,
        public ?string $reason,
    ) {}

    public static function exact(Story $story, string $reason): self
    {
        return new self(true, $story, $reason);
    }

    public static function noMatch(): self
    {
        return new self(false, null, null);
    }
}
