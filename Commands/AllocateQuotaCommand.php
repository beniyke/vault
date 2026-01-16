<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to allocate storage quota to an account.
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
use Vault\Services\VaultManagerService;

class AllocateQuotaCommand extends Command
{
    private VaultManagerService $vaultManager;

    public function __construct(VaultManagerService $vaultManager)
    {
        parent::__construct();
        $this->vaultManager = $vaultManager;
    }

    protected function configure(): void
    {
        $this->setName('vault:allocate')
            ->setDescription('Allocate storage quota to an account')
            ->addArgument('account', InputArgument::REQUIRED, 'Account ID')
            ->addArgument('quota', InputArgument::REQUIRED, 'Quota in MB');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $accountId = $input->getArgument('account');
        $quotaMB = (int) $input->getArgument('quota');

        $io->title('Vault - Allocate Storage Quota');

        try {
            $this->vaultManager->allocate($accountId, $quotaMB);

            $io->success("Successfully allocated {$quotaMB}MB to account: {$accountId}");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $io->error("Failed to allocate quota: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
