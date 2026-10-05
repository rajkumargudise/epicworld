<?php

namespace App\Services\Ai;

use App\Models\Setting;
use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\Exceptions\UnknownAiProviderException;
use App\Services\Ai\Providers\FakeAiProvider;
use App\Services\Ai\Providers\GeminiProvider;
use App\Services\Ai\Providers\OpenAiProvider;

/**
 * Resolves the one AI provider a call should use. Selection is
 * always explicit: a driver name (from config('ai.default'), or
 * passed directly) maps to exactly one adapter, with no attempt at
 * any other driver if that one is misconfigured or fails - a
 * misconfigured driver throws here, before any provider call is
 * ever made, and a provider-level failure during respond() is
 * reported as an AiResult, never retried against a different driver.
 *
 * Adding a provider means adding one case to the match below - no
 * editorial service changes.
 */
class AiProviderManager
{
    public function resolve(?string $driver = null): AiProvider
    {
        $driver ??= $this->defaultDriver();

        $config = config("ai.providers.{$driver}");

        if (! is_array($config)) {
            throw new UnknownAiProviderException((string) $driver);
        }

        return match ($config['driver'] ?? $driver) {
            'gemini' => new GeminiProvider([
                'api_key' => Setting::read('gemini_api_key') ?? $config['api_key'] ?? null,
                'model' => Setting::read('gemini_model') ?? $config['model'] ?? 'gemini-flash-latest',
                'base_url' => $config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta',
                'timeout' => $config['timeout'] ?? config('ai.timeout', 30),
            ]),
            'openai' => new OpenAiProvider([
                'api_key' => Setting::read('openai_api_key') ?? $config['api_key'] ?? null,
                'model' => Setting::read('openai_model') ?? $config['model'] ?? 'gpt-4o-mini',
                'base_url' => $config['base_url'] ?? 'https://api.openai.com/v1',
                'timeout' => $config['timeout'] ?? config('ai.timeout', 30),
            ]),
            'fake' => app(FakeAiProvider::class),
            default => throw new UnknownAiProviderException((string) $driver),
        };
    }

    /**
     * Whether the provider a call would use right now can possibly
     * succeed: the fake provider always can, a real one needs an API key.
     */
    public function isReady(): bool
    {
        $driver = $this->defaultDriver();

        if ($driver === 'fake') {
            return true;
        }

        $key = match ($driver) {
            'openai' => Setting::read('openai_api_key') ?? config('ai.providers.openai.api_key'),
            'gemini' => Setting::read('gemini_api_key') ?? config('ai.providers.gemini.api_key'),
            default => null,
        };

        return filled($key);
    }

    /**
     * An administrator-selected provider (Admin > Settings) wins over
     * the environment default, but never when the app runs with the
     * "fake" provider (tests), so stored settings can't leak into them.
     */
    private function defaultDriver(): string
    {
        $default = (string) config('ai.default');

        if ($default === 'fake') {
            return $default;
        }

        $chosen = Setting::read('ai_provider');

        return in_array($chosen, ['openai', 'gemini'], true) ? $chosen : $default;
    }
}
