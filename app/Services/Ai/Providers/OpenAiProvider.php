<?php

namespace App\Services\Ai\Providers;

use App\Enums\AiResultStatus;
use App\Services\Ai\AiRequest;
use App\Services\Ai\AiResult;
use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\Support\AiOutputValidator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Adapter for OpenAI's Chat Completions API (the ChatGPT models).
 * Uses JSON mode so the model must answer with a single JSON object,
 * which is then validated against the request's schema exactly like
 * every other provider - nothing about OpenAI's response shape leaks
 * past respond().
 */
class OpenAiProvider implements AiProvider
{
    /**
     * @param  array{api_key: ?string, model: string, base_url: string, timeout: int}  $config
     */
    public function __construct(
        private readonly array $config,
        private readonly AiOutputValidator $validator = new AiOutputValidator,
    ) {}

    public function name(): string
    {
        return 'openai';
    }

    public function respond(AiRequest $request): AiResult
    {
        $apiKey = $this->config['api_key'] ?? null;

        if (blank($apiKey)) {
            return AiResult::failure(AiResultStatus::AuthenticationError, $this->name(), null, 'OpenAI API key is not configured.');
        }

        $model = $request->model ?? $this->config['model'];

        try {
            $response = Http::timeout($this->config['timeout'])
                ->withToken($apiKey)
                ->post(rtrim($this->config['base_url'], '/').'/chat/completions', [
                    'model' => $model,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are an editorial assistant for a news and blog publication. Use only the evidence provided. Never invent facts, quotes, numbers or sources. Reply with a single JSON object and nothing else.'],
                        ['role' => 'user', 'content' => $this->buildPrompt($request)],
                    ],
                ]);
        } catch (ConnectionException) {
            return AiResult::failure(AiResultStatus::Timeout, $this->name(), $model, 'OpenAI request timed out or could not connect.');
        }

        if (in_array($response->status(), [401, 403], true)) {
            return AiResult::failure(AiResultStatus::AuthenticationError, $this->name(), $model, 'OpenAI rejected the request credentials.');
        }

        if ($response->status() === 429) {
            return AiResult::failure(AiResultStatus::RateLimited, $this->name(), $model, 'OpenAI rate-limited this request.');
        }

        if ($response->failed()) {
            return AiResult::failure(AiResultStatus::ProviderError, $this->name(), $model, "OpenAI returned HTTP {$response->status()}.");
        }

        $text = $response->json('choices.0.message.content');

        if (! is_string($text) || trim($text) === '') {
            return AiResult::failure(AiResultStatus::InvalidResponse, $this->name(), $model, 'OpenAI response did not contain generated text.');
        }

        $decoded = json_decode(trim($text), true);

        if (! is_array($decoded)) {
            return AiResult::failure(AiResultStatus::InvalidResponse, $this->name(), $model, 'OpenAI output was not valid JSON.');
        }

        $validation = $this->validator->validate($decoded, $request->schema);

        if (! $validation['valid']) {
            return AiResult::failure(AiResultStatus::InvalidResponse, $this->name(), $model, 'OpenAI output failed schema validation: '.implode('; ', $validation['errors']));
        }

        return AiResult::success($this->name(), $model, $decoded);
    }

    private function buildPrompt(AiRequest $request): string
    {
        return implode("\n\n", [
            "Operation: {$request->operation->value}",
            "Instructions:\n{$request->instructions}",
            $request->allowBackground
                ? 'Source material (ground every event-specific claim in this; you may add widely known background, definitions and context, but never invent specific figures, quotes, dates, names or events): '.json_encode($request->facts)
                : 'Evidence (the only facts you may use): '.json_encode($request->facts),
            'Respond with a single JSON object matching this shape (no prose, no markdown fences): '
                .json_encode(array_keys($request->schema)),
        ]);
    }
}
