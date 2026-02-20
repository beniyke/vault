<?php

declare(strict_types=1);

namespace Vault\Models;

use Database\BaseModel;

class VaultFile extends BaseModel
{
    public const TABLE = 'vault_file';

    protected string $table = self::TABLE;

    protected array $fillable = [
        'account_id',
        'refid',
        'file_path',
        'file_size',
        'file_hash',
        'uploaded_at',
        'deleted_at',
    ];

    protected array $casts = [
        'uploaded_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
