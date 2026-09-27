<?php

namespace Tests\Unit\Evidence;

use App\Services\Evidence\Fact;
use PHPUnit\Framework\TestCase;

class FactTest extends TestCase
{
    public function test_it_round_trips_through_array(): void
    {
        $data = ['source_id' => 5, 'source_name' => 'Example', 'reported' => ['title' => 'A headline', 'summary' => 'A summary']];

        $fact = Fact::fromArray($data);

        $this->assertSame($data, $fact->toArray());
    }

    public function test_reported_values_returns_only_string_values_in_order(): void
    {
        $fact = Fact::fromArray([
            'source_id' => 1,
            'source_name' => 'Example',
            'reported' => ['title' => 'A headline', 'summary' => 'A summary', 'source_url' => 'https://example.com'],
        ]);

        $this->assertSame(['A headline', 'A summary', 'https://example.com'], $fact->reportedValues());
    }

    public function test_missing_fields_default_safely(): void
    {
        $fact = Fact::fromArray([]);

        $this->assertSame(0, $fact->sourceId);
        $this->assertSame('', $fact->sourceName);
        $this->assertSame([], $fact->reported);
        $this->assertSame([], $fact->reportedValues());
    }
}
