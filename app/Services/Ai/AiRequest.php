<?php

namespace App\Services\Ai;

use App\Enums\AiOperation;

/**
 * The one shape every editorial AI call takes, regardless of
 * provider: an explicit operation, the Story it concerns, the
 * evidence ledger it must be constrained to, human-authored
 * instructions for the operation, and the output shape the caller
 * expects back. This replaces an arbitrary prompt string assembled
 * ad hoc across the application - a provider adapter turns this
 * structured request into whatever wire format it needs, but nothing
 * outside the provider adapter should ever build a raw prompt.
 *
 * $facts is the Story's fact sheet (see FactExtractor) - the AI is
 * never treated as an independent source of facts, only as something
 * that must work from this evidence. $schema describes the fields
 * the response must contain, so it can be validated before anything
 * in the editorial domain sees it (see AiOutputValidator).
 */
final readonly class AiRequest
{
    /**
     * @param  array<int, array<string, mixed>>  $facts
     * @param  array<string, array<string, mixed>>  $schema
     */
    public function __construct(
        public AiOperation $operation,
        public int $storyId,
        public array $facts,
        public string $instructions,
        public array $schema,
        public ?string $model = null,
    ) {}
}
