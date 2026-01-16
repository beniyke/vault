<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Exception thrown when storage quota is exceeded.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Vault\Exceptions;

use RuntimeException;

class QuotaExceededException extends RuntimeException
{
    public function __construct(string $accountId, int $required, int $available)
    {
        parent::__construct(
            "Storage quota exceeded for account '{$accountId}'. Required: {$required} bytes, Available: {$available} bytes"
        );
    }
}
