<?php

namespace Tests\Feature\Ai;

use App\Enums\AiOperation;
use App\Enums\AiResultStatus;
use App\Services\Ai\AiRequest;
use App\Services\Ai\Providers\GeminiProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiProviderTest extends TestCase
{
    public function test_a_valid_response_is_accepted_and_normalized(): void
    {
        Http::fake([
            '*generativelanguage*' => Http::response($this->geminiEnvelope(json_encode([
                'title' => 'A generated title',
                'body' => 'A generated body.',
            ])), 200),
        ]);

        $result = $this->provider()->respond($this->request());

        $this->assertTrue($result->successful());
        $this->assertSame('gemini', $result->provider);
        $this->assertSame('A generated title', $result->data['title']);
    }

    public function test_missing_api_key_fails_without_making_a_request(): void
    {
        Http::fake();
        $provider = new GeminiProvider(['api_key' => null, 'model' => 'gemini-flash-latest', 'base_url' => 'https://generativelanguage.googleapis.com/v1beta', 'timeout' => 30]);

        $result = $provider->respond($this->request());

        $this->assertSame(AiResultStatus::AuthenticationError, $result->status);
        Http::assertNothingSent();
    }

    public function test_malformed_json_output_is_rejected(): void
    {
        Http::fake([
            '*generativelanguage*' => Http::response($this->geminiEnvelope('{not valid json'), 200),
        ]);

        $result = $this->provider()->respond($this->request());

        $this->assertSame(AiResultStatus::InvalidResponse, $result->status);
        $this->assertStringContainsString('not valid JSON', $result->error);
    }

    public function test_output_missing_a_required_field_is_rejected(): void
    {
        Http::fake([
            '*generativelanguage*' => Http::response($this->geminiEnvelope(json_encode([
                'title' => 'A generated title',
            ])), 200),
        ]);

        $result = $this->provider()->respond($this->request());

        $this->assertSame(AiResultStatus::InvalidResponse, $result->status);
        $this->assertStringContainsString('body', $result->error);
    }

    public function test_an_empty_response_is_rejected(): void
    {
        Http::fake([
            '*generativelanguage*' => Http::response(['candidates' => []], 200),
        ]);

        $result = $this->provider()->respond($this->request());

        $this->assertSame(AiResultStatus::InvalidResponse, $result->status);
    }

    public function test_an_authentication_failure_from_the_provider_is_normalized(): void
    {
        Http::fake([
            '*generativelanguage*' => Http::response(['error' => 'unauthorized'], 401),
        ]);

        $result = $this->provider()->respond($this->request());

        $this->assertSame(AiResultStatus::AuthenticationError, $result->status);
    }

    public function test_a_rate_limit_response_is_normalized(): void
    {
        Http::fake([
            '*generativelanguage*' => Http::response(['error' => 'rate limited'], 429),
        ]);

        $result = $this->provider()->respond($this->request());

        $this->assertSame(AiResultStatus::RateLimited, $result->status);
    }

    public function test_a_generic_provider_error_is_normalized(): void
    {
        Http::fake([
            '*generativelanguage*' => Http::response(['error' => 'server error'], 500),
        ]);

        $result = $this->provider()->respond($this->request());

        $this->assertSame(AiResultStatus::ProviderError, $result->status);
    }

    public function test_a_connection_timeout_is_normalized(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out.');
        });

        $result = $this->provider()->respond($this->request());

        $this->assertSame(AiResultStatus::Timeout, $result->status);
    }

    public function test_the_error_message_never_contains_the_api_key(): void
    {
        Http::fake([
            '*generativelanguage*' => Http::response(['error' => 'unauthorized'], 401),
        ]);

        $result = $this->provider()->respond($this->request());

        $this->assertStringNotContainsString('super-secret-key', $result->error ?? '');
    }

    public function test_the_api_key_travels_as_a_header_never_in_the_request_url(): void
    {
        Http::fake([
            '*generativelanguage*' => Http::response($this->geminiEnvelope(json_encode([
                'title' => 'A generated title',
                'body' => 'A generated body.',
            ])), 200),
        ]);

        $this->provider()->respond($this->request());

        Http::assertSent(function ($request) {
            return ! str_contains($request->url(), 'super-secret-key')
                && $request->hasHeader('x-goog-api-key', 'super-secret-key');
        });
    }

    private function provider(): GeminiProvider
    {
        return new GeminiProvider([
            'api_key' => 'super-secret-key',
            'model' => 'gemini-flash-latest',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'timeout' => 5,
        ]);
    }

    private function request(): AiRequest
    {
        return new AiRequest(
            operation: AiOperation::ArticleGeneration,
            storyId: 1,
            facts: [['source_name' => 'Example', 'reported' => ['title' => 'Reported headline']]],
            instructions: 'Draft the article from the evidence only.',
            schema: ['title' => ['type' => 'string'], 'body' => ['type' => 'string']],
        );
    }

    private function geminiEnvelope(string $text): array
    {
        return [
            'candidates' => [
                ['content' => ['parts' => [['text' => $text]]]],
            ],
        ];
    }
}
