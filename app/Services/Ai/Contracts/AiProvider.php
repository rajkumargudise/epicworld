<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\AiRequest;
use App\Services\Ai\AiResult;

/**
 * What every AI provider adapter must do: turn a structured
 * AiRequest into a normalized AiResult. Nothing outside an adapter's
 * own implementation may depend on Gemini, OpenAI, or any other
 * provider's SDK, response shape, or error types - respond() never
 * throws a provider-specific exception; every failure mode is a
 * status on the returned AiResult instead.
 */
interface AiProvider
{
    /**
     * The config-facing driver name (e.g. "gemini", "fake") - used to
     * tag AiResult::$provider without the caller needing to know it.
     */
    public function name(): string;

    public function respond(AiRequest $request): AiResult;
}
