<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Migration to create vault backups table.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

use Database\Migration\BaseMigration;

class CreateVaultBackupTable extends BaseMigration
{
    public function up(): void
    {
        $this->schema()->create('vault_backup', function ($table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('refid')->unique()->index();
            $table->string('backup_path', 500);
            $table->unsignedBigInteger('backup_size');
            $table->datetime('created_at')->nullable();
            $table->datetime('expires_at')->nullable();

            $table->index('account_id');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('vault_backup');
    }
}
