<?php

namespace App\Enums;

/**
 * Every outcome the AI provider abstraction can normalize a call
 * down to. The editorial domain branches on this instead of on
 * provider-specific exceptions or response shapes.
 */
enum AiResultStatus: string
{
    case Success = 'success';
    case InvalidResponse = 'invalid_response';
    case ProviderError = 'provider_error';
    case Timeout = 'timeout';
    case RateLimited = 'rate_limited';
    case AuthenticationError = 'authentication_error';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'Success',
            self::InvalidResponse => 'Invalid Response',
            self::ProviderError => 'Provider Error',
            self::Timeout => 'Timeout',
            self::RateLimited => 'Rate Limited',
            self::AuthenticationError => 'Authentication Error',
        };
    }
}
