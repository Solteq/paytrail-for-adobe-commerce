<?php

namespace Paytrail\PaymentService\Cron;

use Paytrail\PaymentService\Model\Recurring\Config;
use Paytrail\PaymentService\Model\Recurring\HangingOrderCanceller;

class CancelHangingSubscriptionOrders
{
    /**
     * CancelHangingSubscriptionOrders constructor.
     *
     * @param HangingOrderCanceller $hangingOrderCanceller
     * @param Config $recurringConfig
     */
    public function __construct(
        private readonly HangingOrderCanceller $hangingOrderCanceller,
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
            $this->hangingOrderCanceller->process();
        }
    }
}
