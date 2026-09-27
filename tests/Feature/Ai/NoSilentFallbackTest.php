<?php

namespace Tests\Feature\Ai;

use App\Enums\AiOperation;
use App\Enums\AiResultStatus;
use App\Services\Ai\AiProviderManager;
use App\Services\Ai\AiRequest;
use App\Services\Ai\Providers\FakeAiProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Proves the requirement in the Milestone 6 mandate directly: when
 * the selected provider fails, the operation fails clearly and no
 * other configured provider is ever invoked.
 */
class NoSilentFallbackTest extends TestCase
{
    public function test_a_failing_selected_provider_never_triggers_a_call_to_another_configured_provider(): void
    {
        config([
            'ai.default' => 'gemini',
            'ai.providers.gemini.api_key' => null, // guarantees a clean AuthenticationError, no HTTP call
        ]);
        Http::fake();

        // Registered and available, but never selected - if any
        // fallback logic existed, this is the provider it would
        // reach for next.
        $fallback = app(FakeAiProvider::class);

        $result = app(AiProviderManager::class)->resolve()->respond($this->request());

        $this->assertSame(AiResultStatus::AuthenticationError, $result->status);
        $this->assertSame('gemini', $result->provider);
        Http::assertNothingSent();
        $this->assertSame(0, $fallback->callCount());
    }

    public function test_a_provider_error_response_still_never_reaches_a_second_provider(): void
    {
        config(['ai.default' => 'gemini', 'ai.providers.gemini.api_key' => 'super-secret-key']);
        Http::fake(['*generativelanguage*' => Http::response(['error' => 'boom'], 500)]);
        $fallback = app(FakeAiProvider::class);

        $result = app(AiProviderManager::class)->resolve()->respond($this->request());

        $this->assertSame(AiResultStatus::ProviderError, $result->status);
        Http::assertSentCount(1);
        $this->assertSame(0, $fallback->callCount());
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
