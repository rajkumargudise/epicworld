<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\Exceptions\UnknownAiProviderException;
use App\Services\Ai\Providers\FakeAiProvider;
use App\Services\Ai\Providers\GeminiProvider;

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
        $driver ??= config('ai.default');

        $config = config("ai.providers.{$driver}");

        if (! is_array($config)) {
            throw new UnknownAiProviderException((string) $driver);
        }

        return match ($config['driver'] ?? $driver) {
            'gemini' => new GeminiProvider([
                'api_key' => $config['api_key'] ?? null,
                'model' => $config['model'] ?? 'gemini-2.0-flash',
                'base_url' => $config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta',
                'timeout' => $config['timeout'] ?? config('ai.timeout', 30),
            ]),
            'fake' => app(FakeAiProvider::class),
            default => throw new UnknownAiProviderException((string) $driver),
        };
    }
}
