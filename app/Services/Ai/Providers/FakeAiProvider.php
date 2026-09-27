<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\AiRequest;
use App\Services\Ai\AiResult;
use App\Services\Ai\Contracts\AiProvider;

/**
 * A deterministic, in-memory provider for tests. No network call is
 * ever made, so tests never need internet access or real
 * credentials. Queue up the AiResult(s) each call should return with
 * push(); respond() returns them in order, and repeats the last one
 * once the queue is exhausted so a test that forgets to queue enough
 * results still gets a defined (not a crash) answer. calls() records
 * every AiRequest passed in, so a test can prove this provider was -
 * or was not - invoked (e.g. proving no silent fallback occurred).
 */
class FakeAiProvider implements AiProvider
{
    /** @var array<int, AiResult> */
    private array $queue = [];

    /** @var array<int, AiRequest> */
    private array $calls = [];

    public function push(AiResult $result): static
    {
        $this->queue[] = $result;

        return $this;
    }

    public function respond(AiRequest $request): AiResult
    {
        $this->calls[] = $request;

        if ($this->queue === []) {
            return AiResult::success($this->name(), $request->model, ['note' => 'fake default response']);
        }

        return count($this->queue) > 1 ? array_shift($this->queue) : $this->queue[0];
    }

    public function name(): string
    {
        return 'fake';
    }

    /**
     * @return array<int, AiRequest>
     */
    public function calls(): array
    {
        return $this->calls;
    }

    public function callCount(): int
    {
        return count($this->calls);
    }

    public function reset(): void
    {
        $this->queue = [];
        $this->calls = [];
    }
}
