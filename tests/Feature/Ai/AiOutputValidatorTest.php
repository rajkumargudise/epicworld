<?php

namespace Tests\Feature\Ai;

use App\Services\Ai\Support\AiOutputValidator;
use Tests\TestCase;

class AiOutputValidatorTest extends TestCase
{
    private function schema(): array
    {
        return [
            'title' => ['type' => 'string', 'max_length' => 20],
            'body' => ['type' => 'string'],
            'tags' => ['type' => 'array', 'required' => false],
        ];
    }

    public function test_a_fully_matching_payload_is_valid(): void
    {
        $result = (new AiOutputValidator)->validate(
            ['title' => 'A title', 'body' => 'Body text'],
            $this->schema(),
        );

        $this->assertTrue($result['valid']);
        $this->assertSame([], $result['errors']);
    }

    public function test_an_optional_field_may_be_omitted(): void
    {
        $result = (new AiOutputValidator)->validate(
            ['title' => 'A title', 'body' => 'Body text'],
            $this->schema(),
        );

        $this->assertTrue($result['valid']);
    }

    public function test_a_missing_required_field_is_rejected(): void
    {
        $result = (new AiOutputValidator)->validate(['title' => 'A title'], $this->schema());

        $this->assertFalse($result['valid']);
        $this->assertContains('Missing required field: body', $result['errors']);
    }

    public function test_a_wrong_type_is_rejected(): void
    {
        $result = (new AiOutputValidator)->validate(
            ['title' => 'A title', 'body' => ['not' => 'a string']],
            $this->schema(),
        );

        $this->assertFalse($result['valid']);
        $this->assertContains('Field body expected type string', $result['errors']);
    }

    public function test_content_over_max_length_is_rejected(): void
    {
        $result = (new AiOutputValidator)->validate(
            ['title' => str_repeat('x', 21), 'body' => 'Body text'],
            $this->schema(),
        );

        $this->assertFalse($result['valid']);
        $this->assertContains('Field title exceeds max_length of 20', $result['errors']);
    }

    public function test_an_empty_payload_reports_every_missing_required_field(): void
    {
        $result = (new AiOutputValidator)->validate([], $this->schema());

        $this->assertFalse($result['valid']);
        $this->assertContains('Missing required field: title', $result['errors']);
        $this->assertContains('Missing required field: body', $result['errors']);
        $this->assertCount(2, $result['errors']);
    }
}
