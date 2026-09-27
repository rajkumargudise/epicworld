<?php

namespace Tests\Feature\Ai;

use App\Enums\AiOperation;
use App\Enums\AiResultStatus;
use App\Services\Ai\AiRequest;
use App\Services\Ai\AiResult;
use App\Services\Ai\Providers\FakeAiProvider;
use Tests\TestCase;

class FakeAiProviderTest extends TestCase
{
    public function test_it_returns_a_default_success_result_when_nothing_is_queued(): void
    {
        $provider = new FakeAiProvider;

        $result = $provider->respond($this->request());

        $this->assertTrue($result->successful());
        $this->assertSame('fake', $result->provider);
    }

    public function test_it_returns_queued_results_in_order(): void
    {
        $provider = new FakeAiProvider;
        $success = AiResult::success('fake', null, ['title' => 'Ok']);
        $failure = AiResult::failure(AiResultStatus::Timeout, 'fake', null, 'Simulated timeout.');
        $provider->push($success)->push($failure);

        $first = $provider->respond($this->request());
        $second = $provider->respond($this->request());

        $this->assertSame($success, $first);
        $this->assertSame($failure, $second);
    }

    public function test_it_repeats_the_last_queued_result_once_exhausted(): void
    {
        $provider = new FakeAiProvider;
        $only = AiResult::success('fake', null, ['title' => 'Ok']);
        $provider->push($only);

        $provider->respond($this->request());
        $second = $provider->respond($this->request());

        $this->assertSame($only, $second);
    }

    public function test_it_records_every_call(): void
    {
        $provider = new FakeAiProvider;

        $provider->respond($this->request());
        $provider->respond($this->request());

        $this->assertSame(2, $provider->callCount());
        $this->assertCount(2, $provider->calls());
    }

    private function request(): AiRequest
    {
        return new AiRequest(
            operation: AiOperation::ArticleGeneration,
            storyId: 1,
            facts: [],
            instructions: 'Draft the article.',
            schema: ['title' => ['type' => 'string']],
        );
    }
}
