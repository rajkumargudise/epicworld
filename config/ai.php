<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | The provider AiProviderManager resolves when no driver is passed
    | explicitly. Selection is always explicit and never falls back to
    | another provider if this one is unavailable or misconfigured -
    | the operation fails clearly instead. In testing, this is set to
    | "fake" (see phpunit.xml) so tests never require credentials or
    | network access.
    |
    */

    'default' => env('AI_PROVIDER', 'gemini'),

    /*
    |--------------------------------------------------------------------------
    | Default Timeout
    |--------------------------------------------------------------------------
    |
    | Seconds a provider call may take before it is treated as a
    | timeout failure. A provider entry below may override this with
    | its own "timeout" key.
    |
    */

    'timeout' => (int) env('AI_TIMEOUT_SECONDS', 30),

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    |
    | Every registered provider's configuration. "driver" selects the
    | adapter AiProviderManager instantiates; the rest of each entry is
    | that adapter's own configuration. Never put a credential
    | anywhere but here, and never read env() for one outside this
    | file - AiProviderManager and the adapters are the only things
    | that should see these values.
    |
    */

    'providers' => [

        'gemini' => [
            'driver' => 'gemini',
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-flash-latest'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'timeout' => env('GEMINI_TIMEOUT_SECONDS', 90),
        ],

        'openai' => [
            'driver' => 'openai',
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'timeout' => env('OPENAI_TIMEOUT_SECONDS', 90),
        ],

        'fake' => [
            'driver' => 'fake',
        ],

    ],

];
