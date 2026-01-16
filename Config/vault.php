<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Vault configuration.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Storage Quota
    |--------------------------------------------------------------------------
    |
    | Default storage quota allocated to new accounts (in megabytes)
    |
    */
    'default_quota_mb' => env('VAULT_DEFAULT_QUOTA_MB', 1024),

    /*
    |--------------------------------------------------------------------------
    | Maximum Storage Quota
    |--------------------------------------------------------------------------
    |
    | Maximum storage quota that can be allocated (in megabytes)
    |
    */
    'max_quota_mb' => env('VAULT_MAX_QUOTA_MB', 102400),

    /*
    |--------------------------------------------------------------------------
    | Storage Path
    |--------------------------------------------------------------------------
    |
    | Base directory for account storage
    |
    */
    'storage_path' => 'App/storage/vault',

    /*
    |--------------------------------------------------------------------------
    | Backup Path
    |--------------------------------------------------------------------------
    |
    | Directory for storing backups
    |
    */
    'backup_path' => 'App/storage/vault-backups',

    /*
    |--------------------------------------------------------------------------
    | Account Resolver
    |--------------------------------------------------------------------------
    |
    | Callback function to resolve account ID from the request.
    | This allows you to customize how the middleware identifies accounts
    | without modifying system files.
    |
    | Examples:
    | - From header: fn($request) => $request->header('X-Account-ID')
    | - From auth: fn($request) => auth()->user()?->account_id
    | - From session: fn($request) => session()->get('account_id')
    | - From JWT: fn($request) => jwt()->decode($request->getBearerToken())?->account_id
    |
    */
    'account_resolver' => fn ($request) => $request->header('X-Account-ID'),

    /*
    |--------------------------------------------------------------------------
    | Backup Retention
    |--------------------------------------------------------------------------
    |
    | Number of days to retain backups before automatic cleanup
    |
    */
    'backup_retention_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | File Tracking
    |--------------------------------------------------------------------------
    |
    | Enable detailed file tracking in database
    |
    */
    'enable_file_tracking' => true,

    /*
    |--------------------------------------------------------------------------
    | Deduplication
    |--------------------------------------------------------------------------
    |
    | Enable file deduplication based on content hash
    |
    */
    'enable_deduplication' => false,

    /*
    |--------------------------------------------------------------------------
    | Compression
    |--------------------------------------------------------------------------
    |
    | Enable compression for backups
    |
    */
    'enable_compression' => true,

    /*
    |--------------------------------------------------------------------------
    | Maximum File Size
    |--------------------------------------------------------------------------
    |
    | Maximum individual file size allowed (in megabytes)
    |
    */
    'max_file_size_mb' => 100,

    /*
    |--------------------------------------------------------------------------
    | Allowed Extensions
    |--------------------------------------------------------------------------
    |
    | Allowed file extensions. Use ['*'] to allow all extensions.
    |
    */
    'allowed_extensions' => ['*'],
];
