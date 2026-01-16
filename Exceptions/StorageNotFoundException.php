<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Exception thrown when storage is not found for an account.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Vault\Exceptions;

use RuntimeException;

class StorageNotFoundException extends RuntimeException
{
    public function __construct(string $accountId)
    {
        parent::__construct("Storage not found for account: {$accountId}");
    }
}
