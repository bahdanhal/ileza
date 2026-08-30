<?php

declare(strict_types=1);

namespace App\Command;

use App\Market\Application\CheckPriceAlerts;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'market:check-price-alerts',
    description: 'Check active price drop alert subscriptions and dispatch notification emails.'
)]
final class CheckPriceAlertsCommand extends Command
{
    public function __construct(
        private readonly CheckPriceAlerts $checkPriceAlerts,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('product', 'p', InputOption::VALUE_OPTIONAL, 'Filter checks to a specific product slug')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate notification evaluation without sending emails');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Market Price Drop Alert Check');

        /** @var string|null $productSlug */
        $productSlug = $input->getOption('product');
        $dryRun = (bool) $input->getOption('dry-run');

        if ($dryRun) {
            $io->note('Running in DRY-RUN mode. No emails will be sent and alert states will not change.');
        }

        $result = $this->checkPriceAlerts->execute($productSlug, $dryRun);

        $tableRows = array_map(static fn (array $detail): array => [
            $detail['product'],
            $detail['email'],
            number_format($detail['current_price_grosz'] / 100, 0) . ' zł',
            $detail['target_price_grosz'] !== null ? number_format($detail['target_price_grosz'] / 100, 0) . ' zł' : 'Any drop',
            $detail['notified'] ? '✓ Sent' : '- Skipped',
        ], $result['details']);

        if (!empty($tableRows)) {
            $io->table(['Product', 'Subscriber', 'Current Median', 'Target Threshold', 'Status'], $tableRows);
        }

        $io->success(sprintf(
            'Check completed: %d products checked, %d active subscribers evaluated, %d notifications %s.',
            $result['checked_products'],
            $result['active_subscribers'],
            $result['notifications_sent'],
            $dryRun ? 'simulated' : 'dispatched'
        ));

        return Command::SUCCESS;
    }
}
