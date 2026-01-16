<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to wipe all storage for an account.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Vault\Commands;

use Helpers\File\Adapters\Interfaces\FileManipulationInterface;
use Helpers\File\Adapters\Interfaces\FileMetaInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use Vault\Services\BackupService;
use Vault\Services\VaultManagerService;

class WipeStorageCommand extends Command
{
    private VaultManagerService $vaultManager;

    private BackupService $backupService;

    private FileMetaInterface $fileMeta;

    private FileManipulationInterface $fileManipulation;

    public function __construct(
        VaultManagerService $vaultManager,
        BackupService $backupService,
        FileMetaInterface $fileMeta,
        FileManipulationInterface $fileManipulation
    ) {
        parent::__construct();
        $this->vaultManager = $vaultManager;
        $this->backupService = $backupService;
        $this->fileMeta = $fileMeta;
        $this->fileManipulation = $fileManipulation;
    }

    protected function configure(): void
    {
        $this->setName('vault:wipe')
            ->setDescription('Wipe all storage for an account')
            ->addArgument('account', InputArgument::REQUIRED, 'Account ID')
            ->addOption('backup', 'b', InputOption::VALUE_NONE, 'Create backup before wiping')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $accountId = $input->getArgument('account');
        $createBackup = $input->getOption('backup');
        $force = $input->getOption('force');

        $io->title('Vault - Wipe Storage');
        $io->warning("This will permanently delete all files for account: {$accountId}");

        if (! $force && ! $io->confirm('Are you sure you want to continue?', false)) {
            $io->note('Operation cancelled');

            return self::SUCCESS;
        }

        try {
            if ($createBackup) {
                $io->text('Creating backup...');
                $backupPath = $this->backupService->create($accountId);
                $io->success("Backup created: {$backupPath}");
            }

            $storagePath = $this->vaultManager->getStoragePath($accountId);

            if ($this->fileMeta->isDir($storagePath)) {
                $this->fileManipulation->delete($storagePath);
                $this->fileManipulation->mkdir($storagePath, 0755, true);
            }

            $this->vaultManager->recalculateUsage($accountId);

            $io->success("Storage wiped successfully for account: {$accountId}");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $io->error("Failed to wipe storage: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
