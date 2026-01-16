<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to create a backup of account storage.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Vault\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use Vault\Services\BackupService;

class CreateBackupCommand extends Command
{
    private BackupService $backupService;

    public function __construct(BackupService $backupService)
    {
        parent::__construct();
        $this->backupService = $backupService;
    }

    protected function configure(): void
    {
        $this->setName('vault:backup')
            ->setDescription('Create a backup of account storage')
            ->addArgument('account', InputArgument::REQUIRED, 'Account ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $accountId = $input->getArgument('account');

        $io->title('Vault - Create Backup');
        $io->text("Creating backup for account: {$accountId}");

        try {
            $backupPath = $this->backupService->create($accountId);

            $io->success('Backup created successfully!');
            $io->definitionList(
                ['Backup Path' => $backupPath],
                ['Size' => $this->formatBytes(filesize($backupPath))]
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            $io->error("Failed to create backup: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
