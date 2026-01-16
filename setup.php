<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Vault Package Setup Manifest.
 * This file defines what gets registered when the Vault package is installed.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

return [
    'providers' => [
        Vault\Providers\VaultServiceProvider::class,
    ],
    'middleware' => [
        'api' => [
            Vault\Middleware\CheckVaultQuotaMiddleware::class,
        ]
    ]
];
