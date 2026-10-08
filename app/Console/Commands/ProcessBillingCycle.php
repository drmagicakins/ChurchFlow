<?php

namespace App\Console\Commands;

use App\Domains\Subscriptions\Services\RenewalAndDunningService;
use Illuminate\Console\Command;

class ProcessBillingCycle extends Command
{
    protected $signature = 'subscriptions:process-billing-cycle';

    protected $description = 'Attempts renewals, advances dunning states, expires overdue subscriptions, and finalizes requested cancellations.';

    public function handle(RenewalAndDunningService $service): int
    {
        $counts = $service->runDailyCycle();

        $this->info(sprintf(
            'Renewed: %d, Past due: %d, Grace period: %d, Expired: %d, Cancelled: %d',
            $counts['renewed'], $counts['past_due'], $counts['grace_period'], $counts['expired'], $counts['cancelled'],
        ));

        return self::SUCCESS;
    }
}
