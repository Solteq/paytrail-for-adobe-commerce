<?php

namespace Paytrail\PaymentService\Cron;

use Paytrail\PaymentService\Model\Recurring\RecurringOrderCloner;
use Paytrail\PaymentService\Model\Recurring\TotalConfigProvider;

class RecurringPaymentNotify
{
    /**
     * RecurringPaymentNotify constructor.
     *
     * @param RecurringOrderCloner $recurringOrderCloner
     * @param TotalConfigProvider $totalConfigProvider
     */
    public function __construct(
        private readonly RecurringOrderCloner $recurringOrderCloner,
        private readonly TotalConfigProvider $totalConfigProvider
    ) {
    }

    /**
     * Execute
     *
     * @return void
     */
    public function execute(): void
    {
        if ($this->totalConfigProvider->isRecurringPaymentEnabled()) {
            $this->recurringOrderCloner->process();
        }
    }
}
