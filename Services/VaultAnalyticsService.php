<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Vault Analytics Service
 *
 * Provides performant reporting and analytics for storage usage.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Vault\Services;

use Database\DB;

class VaultAnalyticsService
{
    private const VAULT_TABLE = 'vault_quota';
    private const FILE_TABLE = 'vault_file';

    /**
     * Get platform-wide storage overview.
     */
    public function getPlatformOverview(): array
    {
        $stats = DB::table(self::VAULT_TABLE)
            ->select(
                DB::raw('COUNT(*) as total_accounts'),
                DB::raw('SUM(quota_bytes) as total_quota'),
                DB::raw('SUM(used_bytes) as total_used'),
                DB::raw('AVG(CASE WHEN quota_bytes > 0 THEN (used_bytes * 100.0 / quota_bytes) ELSE 0 END) as avg_usage_percent')
            )
            ->first();

        return [
            'total_accounts' => (int) $stats->total_accounts,
            'total_quota_bytes' => (float) $stats->total_quota,
            'total_used_bytes' => (float) $stats->total_used,
            'avg_usage_percent' => round((float) $stats->avg_usage_percent, 2),
            'free_space_bytes' => (float) ($stats->total_quota - $stats->total_used),
        ];
    }

    public function getTopAccounts(int $limit = 10): array
    {
        return DB::table(self::VAULT_TABLE)
            ->orderBy('used_bytes', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getUsageDistribution(): array
    {
        $distribution = DB::table(self::VAULT_TABLE)
            ->select(
                DB::raw("CASE 
                    WHEN quota_bytes = 0 THEN 'Error'
                    WHEN (used_bytes * 100.0 / quota_bytes) < 25 THEN '0-25%'
                    WHEN (used_bytes * 100.0 / quota_bytes) < 50 THEN '25-50%'
                    WHEN (used_bytes * 100.0 / quota_bytes) < 75 THEN '50-75%'
                    WHEN (used_bytes * 100.0 / quota_bytes) < 90 THEN '75-90%'
                    ELSE '90-100%+' 
                END as tier"),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('tier')
            ->get();

        $result = [];
        foreach ($distribution as $item) {
            $result[$item->tier] = (int) $item->count;
        }

        return $result;
    }

    public function getUploadTrends(string $from, string $to, string $interval = 'day'): array
    {
        $dateFormat = $interval === 'month' ? '%Y-%m' : '%Y-%m-%d';

        $trends = DB::table(self::FILE_TABLE)
            ->select(
                DB::raw("DATE_FORMAT(uploaded_at, '{$dateFormat}') as date_period"),
                DB::raw('SUM(file_size) as total_bytes'),
                DB::raw('COUNT(*) as file_count')
            )
            ->where('uploaded_at', '>=', $from)
            ->where('uploaded_at', '<=', $to)
            ->whereNull('deleted_at')
            ->groupBy('date_period')
            ->orderBy('date_period', 'asc')
            ->get();

        return array_map(function ($row) {
            return [
                'period' => $row->date_period,
                'bytes' => (float) $row->total_bytes,
                'count' => (int) $row->file_count,
            ];
        }, $trends);
    }

    /**
     * Identify accounts nearing or exceeding their quota.
     */
    public function getAtRiskAccounts(float $thresholdPercent = 90.0): array
    {
        return DB::table(self::VAULT_TABLE)
            ->whereRaw('(used_bytes * 100.0 / quota_bytes) >= ?', [$thresholdPercent])
            ->orderByRaw('(used_bytes * 100.0 / quota_bytes) DESC')
            ->get();
    }
}
