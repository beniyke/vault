<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Command to check storage usage for an account.
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

class CheckUsageCommand extends Command
{
    private VaultManagerService $vaultManager;

    public function __construct(VaultManagerService $vaultManager)
    {
        parent::__construct();
        $this->vaultManager = $vaultManager;
    }

    protected function configure(): void
    {
        $this->setName('vault:usage')
            ->setDescription('Check storage usage for an account')
            ->addArgument('account', InputArgument::REQUIRED, 'Account ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $accountId = $input->getArgument('account');

        $io->title('Vault - Storage Usage');

        try {
            $usage = $this->vaultManager->getUsage($accountId);

            $usedMB = round($usage['used'] / 1048576, 2);
            $quotaMB = round($usage['quota'] / 1048576, 2);
            $remainingMB = round($usage['remaining'] / 1048576, 2);

            $io->definitionList(
                ['Account' => $accountId],
                ['Used' => "{$usedMB} MB"],
                ['Quota' => "{$quotaMB} MB"],
                ['Remaining' => "{$remainingMB} MB"],
                ['Percentage' => "{$usage['percentage']}%"]
            );

            if ($usage['percentage'] >= 90) {
                $io->warning('Storage is nearly full!');
            } elseif ($usage['percentage'] >= 80) {
                $io->note('Storage is over 80% full');
            } else {
                $io->success('Storage usage is healthy');
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $io->error("Failed to check usage: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
