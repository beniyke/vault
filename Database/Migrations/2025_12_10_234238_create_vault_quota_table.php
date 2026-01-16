<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Migration to create vault quotas table.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

use Database\Migration\BaseMigration;

class CreateVaultQuotaTable extends BaseMigration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->schema()->create('vault_quota', function ($table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('refid')->unique()->index();
            $table->unsignedBigInteger('quota_bytes');
            $table->unsignedBigInteger('used_bytes')->default(0);
            $table->dateTimestamps();

            $table->index('account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->schema()->dropIfExists('vault_quota');
    }
}
