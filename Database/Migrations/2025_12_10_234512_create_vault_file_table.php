<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Migration to create vault files table.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

use Database\Migration\BaseMigration;

class CreateVaultFileTable extends BaseMigration
{
    public function up(): void
    {
        $this->schema()->create('vault_file', function ($table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('refid')->unique()->index();
            $table->string('file_path', 500);
            $table->unsignedBigInteger('file_size');
            $table->string('file_hash', 64)->nullable();
            $table->datetime('uploaded_at')->nullable();
            $table->datetime('deleted_at')->nullable();

            $table->index('account_id');
            $table->index('file_hash');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('vault_file');
    }
}
