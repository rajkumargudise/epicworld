<?php

namespace App\Services\Evidence;

/**
 * One source's reported observation of a Story - the smallest unit
 * of evidence in the editorial pipeline. This is a typed wrapper
 * around the shape FactExtractor already writes into
 * Story::facts; it exists so every consumer (quality evaluation,
 * drafting, and the AI provider boundary) reads the same fields the
 * same way instead of each re-deciding what a "fact" array looks
 * like.
 */
final readonly class Fact
{
    /**
     * @param  array<string, string>  $reported
     */
    public function __construct(
        public int $sourceId,
        public string $sourceName,
        public array $reported,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sourceId: (int) ($data['source_id'] ?? 0),
            sourceName: (string) ($data['source_name'] ?? ''),
            reported: $data['reported'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source_id' => $this->sourceId,
            'source_name' => $this->sourceName,
            'reported' => $this->reported,
        ];
    }

    /**
     * Every string value this source actually reported, for a
     * deterministic text search (see FactSheet::supports()) - never
     * for display, since it has no field labels.
     *
     * @return array<int, string>
     */
    public function reportedValues(): array
    {
        return array_values(array_filter($this->reported, 'is_string'));
    }
}
