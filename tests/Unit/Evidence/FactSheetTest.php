<?php

namespace Tests\Unit\Evidence;

use App\Services\Evidence\Fact;
use App\Services\Evidence\FactSheet;
use PHPUnit\Framework\TestCase;

class FactSheetTest extends TestCase
{
    public function test_it_round_trips_through_array_storage_with_no_shape_change(): void
    {
        $rows = [
            ['source_id' => 1, 'source_name' => 'Example', 'reported' => ['title' => 'A headline']],
        ];

        $sheet = FactSheet::fromArray($rows);

        $this->assertSame($rows, $sheet->toArray());
    }

    public function test_an_empty_sheet_is_empty(): void
    {
        $sheet = FactSheet::fromArray([]);

        $this->assertTrue($sheet->isEmpty());
        $this->assertSame(0, $sheet->count());
    }

    public function test_a_populated_sheet_is_not_empty_and_counts_correctly(): void
    {
        $sheet = FactSheet::fromArray([
            ['source_id' => 1, 'source_name' => 'A', 'reported' => ['title' => 'One']],
            ['source_id' => 2, 'source_name' => 'B', 'reported' => ['title' => 'Two']],
        ]);

        $this->assertFalse($sheet->isEmpty());
        $this->assertSame(2, $sheet->count());
    }

    public function test_it_is_iterable_over_fact_objects(): void
    {
        $sheet = FactSheet::fromArray([
            ['source_id' => 1, 'source_name' => 'A', 'reported' => ['title' => 'One']],
        ]);

        $facts = iterator_to_array($sheet);

        $this->assertCount(1, $facts);
        $this->assertInstanceOf(Fact::class, $facts[0]);
        $this->assertSame('A', $facts[0]->sourceName);
    }

    public function test_supports_matches_a_claim_that_appears_verbatim_case_insensitively(): void
    {
        $sheet = FactSheet::fromArray([
            ['source_id' => 1, 'source_name' => 'Example', 'reported' => ['title' => 'The Bridge Reopens Tomorrow']],
        ]);

        $this->assertTrue($sheet->supports('the bridge reopens tomorrow'));
        $this->assertTrue($sheet->supports('Bridge Reopens'));
    }

    public function test_supports_rejects_a_claim_not_present_in_any_fact(): void
    {
        $sheet = FactSheet::fromArray([
            ['source_id' => 1, 'source_name' => 'Example', 'reported' => ['title' => 'The Bridge Reopens Tomorrow']],
        ]);

        $this->assertFalse($sheet->supports('The mayor resigned'));
    }

    public function test_supports_rejects_an_empty_or_blank_claim(): void
    {
        $sheet = FactSheet::fromArray([
            ['source_id' => 1, 'source_name' => 'Example', 'reported' => ['title' => 'Some headline']],
        ]);

        $this->assertFalse($sheet->supports(''));
        $this->assertFalse($sheet->supports('   '));
    }

    public function test_supports_on_an_empty_sheet_is_always_false(): void
    {
        $sheet = FactSheet::fromArray([]);

        $this->assertFalse($sheet->supports('anything'));
    }

    public function test_for_ai_request_matches_to_array(): void
    {
        $rows = [
            ['source_id' => 1, 'source_name' => 'Example', 'reported' => ['title' => 'A headline']],
        ];
        $sheet = FactSheet::fromArray($rows);

        $this->assertSame($sheet->toArray(), $sheet->forAiRequest());
    }
}
