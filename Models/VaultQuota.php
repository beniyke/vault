<?php

declare(strict_types=1);

namespace Vault\Models;

use Database\BaseModel;

class VaultQuota extends BaseModel
{
    public const TABLE = 'vault_quota';

    protected string $table = self::TABLE;

    protected array $fillable = [
        'account_id',
        'refid',
        'quota_bytes',
        'used_bytes',
    ];
}
