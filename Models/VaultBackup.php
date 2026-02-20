<?php

declare(strict_types=1);

namespace Vault\Models;

use Database\BaseModel;

class VaultBackup extends BaseModel
{
    public const TABLE = 'vault_backup';

    protected string $table = self::TABLE;

    protected array $fillable = [
        'account_id',
        'refid',
        'backup_path',
        'backup_size',
        'expires_at',
    ];

    protected array $casts = [
        'expires_at' => 'datetime',
    ];
}
