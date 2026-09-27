<?php

namespace App\Services\Ai;

use App\Enums\AiResultStatus;

/**
 * What every provider adapter returns, win or lose - the normalized
 * boundary the rest of the application consumes instead of a
 * provider-specific response object or exception. $data is present
 * only on Success, and only after AiOutputValidator has confirmed it
 * matches the request's schema; $error is a short, safe-to-log
 * message (never a credential, never raw provider payloads that
 * might carry one).
 */
final readonly class AiResult
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    private function __construct(
        public AiResultStatus $status,
        public string $provider,
        public ?string $model,
        public ?array $data,
        public ?string $error,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function success(string $provider, ?string $model, array $data): self
    {
        return new self(AiResultStatus::Success, $provider, $model, $data, null);
    }

    public static function failure(AiResultStatus $status, string $provider, ?string $model, string $error): self
    {
        if ($status === AiResultStatus::Success) {
            throw new \InvalidArgumentException('Use AiResult::success() for a Success status.');
        }

        return new self($status, $provider, $model, null, $error);
    }

    public function successful(): bool
    {
        return $this->status === AiResultStatus::Success;
    }
}
