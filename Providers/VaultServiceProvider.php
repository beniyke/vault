<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Vault Service Provider.
 * Registers storage quota management services.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Vault\Providers;

use Core\Services\ServiceProvider;
use Vault\Services\BackupService;
use Vault\Services\FileTrackerService;
use Vault\Services\VaultManagerService;

class VaultServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(VaultManagerService::class);
        $this->container->singleton(BackupService::class);
        $this->container->singleton(FileTrackerService::class);
    }
}
