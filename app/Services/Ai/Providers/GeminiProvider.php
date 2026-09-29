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
 * Adapter for Google's Gemini generateContent API. This is the first
 * real provider, chosen because the config example in the mandate
 * names it - the architecture does not privilege it, and a second
 * provider is added purely by registering another driver in
 * AiProviderManager, with no change to any editorial service.
 *
 * Every failure mode - missing credentials, HTTP failure, timeout,
 * malformed body, invalid schema - is normalized into an AiResult
 * here. Nothing about Gemini's response shape, its SDK (there is
 * none - this uses Laravel's HTTP client), or its error format
 * leaks past respond().
 */
class GeminiProvider implements AiProvider
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
        return 'gemini';
    }

    public function respond(AiRequest $request): AiResult
    {
        $apiKey = $this->config['api_key'] ?? null;

        if (blank($apiKey)) {
            return AiResult::failure(
                AiResultStatus::AuthenticationError,
                $this->name(),
                null,
                'Gemini API key is not configured.',
            );
        }

        $model = $request->model ?? $this->config['model'];

        try {
            // Milestone 17: the key travels as the x-goog-api-key
            // header (an alternative Gemini's API explicitly supports)
            // rather than in the URL's query string. A URL is far more
            // likely than a header to end up captured somewhere this
            // application doesn't control - an HTTP client's own debug
            // log, an error tracker's breadcrumb, an intermediate
            // proxy's access log - so keeping the key out of it is a
            // real reduction in where it could leak, not just cosmetic.
            $response = Http::timeout($this->config['timeout'])
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->post(
                    rtrim($this->config['base_url'], '/')."/models/{$model}:generateContent",
                    ['contents' => [['parts' => [['text' => $this->buildPrompt($request)]]]]],
                );
        } catch (ConnectionException) {
            return AiResult::failure(
                AiResultStatus::Timeout,
                $this->name(),
                $model,
                'Gemini request timed out or could not connect.',
            );
        }

        if ($response->status() === 401 || $response->status() === 403) {
            return AiResult::failure(
                AiResultStatus::AuthenticationError,
                $this->name(),
                $model,
                'Gemini rejected the request credentials.',
            );
        }

        if ($response->status() === 429) {
            return AiResult::failure(
                AiResultStatus::RateLimited,
                $this->name(),
                $model,
                'Gemini rate-limited this request.',
            );
        }

        if ($response->failed()) {
            return AiResult::failure(
                AiResultStatus::ProviderError,
                $this->name(),
                $model,
                "Gemini returned HTTP {$response->status()}.",
            );
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($text) || trim($text) === '') {
            return AiResult::failure(
                AiResultStatus::InvalidResponse,
                $this->name(),
                $model,
                'Gemini response did not contain generated text.',
            );
        }

        $decoded = json_decode(trim($text), true);

        if (! is_array($decoded)) {
            return AiResult::failure(
                AiResultStatus::InvalidResponse,
                $this->name(),
                $model,
                'Gemini output was not valid JSON.',
            );
        }

        $validation = $this->validator->validate($decoded, $request->schema);

        if (! $validation['valid']) {
            return AiResult::failure(
                AiResultStatus::InvalidResponse,
                $this->name(),
                $model,
                'Gemini output failed schema validation: '.implode('; ', $validation['errors']),
            );
        }

        return AiResult::success($this->name(), $model, $decoded);
    }

    /**
     * A structured, deterministic prompt built only from the
     * request's own fields - evidence, instructions, and the schema
     * the model must answer in. Nothing else in the application
     * assembles prompt text; this is the one place it happens.
     */
    private function buildPrompt(AiRequest $request): string
    {
        return implode("\n\n", [
            "Operation: {$request->operation->value}",
            "Instructions:\n{$request->instructions}",
            'Evidence (the only facts you may use): '.json_encode($request->facts),
            'Respond with a single JSON object matching this shape (no prose, no markdown fences): '
                .json_encode(array_keys($request->schema)),
        ]);
    }
}
