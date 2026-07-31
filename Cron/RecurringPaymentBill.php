<?php

namespace Paytrail\PaymentService\Cron;

use Paytrail\PaymentService\Model\Recurring\Bill;
use Paytrail\PaymentService\Model\Recurring\Config;

class RecurringPaymentBill
{
    /**
     * RecurringPaymentBill constructor.
     *
     * @param Bill $bill
     * @param Config $recurringConfig
     */
    public function __construct(
        private readonly Bill $bill,
        private readonly Config $recurringConfig
    ) {
    }

    /**
     * Execute
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        if ($this->recurringConfig->isRecurringPaymentEnabled()) {
            $this->bill->process();
        }
    }
}
