<?php

namespace Paytrail\PaymentService\Cron;

use Paytrail\PaymentService\Model\Recurring\Config;
use Paytrail\PaymentService\Model\Recurring\RecurringOrderCloner;

class RecurringPaymentNotify
{
    /**
     * RecurringPaymentNotify constructor.
     *
     * @param RecurringOrderCloner $recurringOrderCloner
     * @param Config $recurringConfig
     */
    public function __construct(
        private readonly RecurringOrderCloner $recurringOrderCloner,
        private readonly Config $recurringConfig
    ) {
    }

    /**
     * Execute
     *
     * @return void
     */
    public function execute(): void
    {
        if ($this->recurringConfig->isRecurringPaymentEnabled()) {
            $this->recurringOrderCloner->process();
        }
    }
}
