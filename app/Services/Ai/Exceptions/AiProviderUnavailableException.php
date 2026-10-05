<?php

namespace App\Services\Ai\Exceptions;

use RuntimeException;

/**
 * The AI provider is unavailable for reasons that have nothing to do
 * with the story being drafted - rate limited / out of quota, rejected
 * credentials, or timing out. The job is returned to the queue
 * untouched and the batch stops, instead of failing every remaining job.
 */
class AiProviderUnavailableException extends RuntimeException {}
