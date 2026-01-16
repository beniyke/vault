<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Vault Analytics Facade
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Vault;

use Vault\Services\VaultAnalyticsService;

class Analytics
{
    public static function __callStatic(string $method, array $arguments)
    {
        return resolve(VaultAnalyticsService::class)->$method(...$arguments);
    }
}
