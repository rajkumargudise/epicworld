<?php

namespace App\Services\Evidence;

use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * A Story's full evidence ledger - an ordered, typed collection of
 * Facts (see Fact) - and the one place the editorial pipeline asks
 * "does the evidence actually support this?" This is the hardening
 * Milestone 6 anticipated: the AI provider boundary accepts a Story's
 * fact sheet as untyped evidence today, and this is what a later
 * editorial service (Milestone 8) will use to check a generated
 * claim against it before accepting the claim, rather than trusting
 * an AI response at face value.
 *
 * Immutable and storage-format-compatible with what FactExtractor
 * already persists into Story::facts - toArray() round-trips through
 * fromArray() with no shape change, so this is additive, not a
 * migration.
 *
 * @implements IteratorAggregate<int, Fact>
 */
final readonly class FactSheet implements Countable, IteratorAggregate
{
    /**
     * @param  array<int, Fact>  $facts
     */
    public function __construct(
        private array $facts = [],
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function fromArray(array $rows): self
    {
        return new self(array_map(Fact::fromArray(...), $rows));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(fn (Fact $fact) => $fact->toArray(), $this->facts);
    }

    public function isEmpty(): bool
    {
        return $this->facts === [];
    }

    public function count(): int
    {
        return count($this->facts);
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->facts);
    }

    /**
     * A deliberately narrow, deterministic check: true only when
     * $claim appears verbatim (case-insensitive, whitespace-trimmed)
     * inside some fact's reported text. This is not linguistic fact-
     * checking and never will be - it is the smallest defensible tool
     * for telling "this exact wording is traceable to a source" from
     * "this is not in the evidence at all". A claim this returns
     * false for is not thereby proven false; it is simply not
     * verifiable against this ledger, which is the signal a future
     * AI-output gate needs.
     */
    public function supports(string $claim): bool
    {
        $needle = mb_strtolower(trim($claim));

        if ($needle === '') {
            return false;
        }

        foreach ($this->facts as $fact) {
            foreach ($fact->reportedValues() as $value) {
                if (str_contains(mb_strtolower($value), $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * A tolerant variant of supports() for AI citations: true when the
     * claim is verbatim in the evidence, OR when most of the claim's
     * meaningful words (default 60%) appear together in a single piece of
     * reported evidence. A faithful paraphrase of a headline passes; a
     * claim whose words are mostly not in the evidence does not.
     */
    public function supportsLoosely(string $claim, float $threshold = 0.6): bool
    {
        if ($this->supports($claim)) {
            return true;
        }

        $claimWords = $this->significantWords($claim);

        if (count($claimWords) < 3) {
            return false;
        }

        foreach ($this->facts as $fact) {
            foreach ($fact->reportedValues() as $value) {
                $valueWords = $this->significantWords((string) $value);

                if ($valueWords === []) {
                    continue;
                }

                $shared = count(array_intersect($claimWords, $valueWords));

                if ($shared / count($claimWords) >= $threshold) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function significantWords(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($words, fn (string $w) => mb_strlen($w) >= 3
            && ! in_array($w, ['the', 'and', 'for', 'with', 'that', 'this', 'from', 'are', 'was', 'were', 'has', 'have', 'had', 'its', 'his', 'her', 'their', 'into', 'over', 'after', 'about'], true))));
    }

    /**
     * The shape an AiRequest carries as evidence. Kept as a distinct
     * method (rather than reusing toArray() by convention) so the
     * AI-facing contract can diverge from the storage shape later
     * without touching how facts are persisted.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forAiRequest(): array
    {
        return $this->toArray();
    }
}
