<?php

namespace App\Services\Ai\Exceptions;

use RuntimeException;

/**
 * Thrown by AiProviderManager when the configured or requested
 * driver has no registered adapter. This is a configuration/wiring
 * failure discovered at resolution time - deliberately an exception
 * rather than an AiResult, since there is no provider yet to tag a
 * result with. It carries only the driver name, never configuration
 * values, so it is always safe to log.
 */
class UnknownAiProviderException extends RuntimeException
{
    public function __construct(string $driver)
    {
        parent::__construct("No AI provider is registered for driver \"{$driver}\".");
    }
}
