<?php

namespace Webkul\DinxCommerce\Console\Commands;

use Illuminate\Console\Command;
use Webkul\DinxCommerce\Services\RecurringInvoiceService;

class RunRecurringInvoicesCommand extends Command
{
    protected $signature = 'dinx-commerce:run-recurring-invoices';

    protected $description = 'Generate invoices for due recurring invoice profiles';

    public function handle(RecurringInvoiceService $service): int
    {
        $result = $service->runDueProfiles(now());

        $this->info(sprintf(
            'Recurring invoice run completed. processed=%d created=%d failed=%d',
            (int) ($result['processed'] ?? 0),
            (int) ($result['created'] ?? 0),
            (int) ($result['failed'] ?? 0)
        ));

        return ((int) ($result['failed'] ?? 0)) > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
