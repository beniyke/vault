<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Exception thrown for invalid quota values.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Vault\Exceptions;

use InvalidArgumentException;

class InvalidQuotaException extends InvalidArgumentException
{
    public static function negative(int $quota): self
    {
        return new self("Quota cannot be negative: {$quota}");
    }

    public static function exceedsMaximum(int $quota, int $max): self
    {
        return new self("Quota {$quota}MB exceeds maximum allowed {$max}MB");
    }

    public static function zero(): self
    {
        return new self("Quota must be greater than zero");
    }
}
