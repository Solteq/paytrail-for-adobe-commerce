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
     * @param Config $config
     */
    public function __construct(
        private readonly Bill $bill,
        private readonly Config $config
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
        if ($this->config->isRecurringPaymentEnabled()) {
            $this->bill->process();
        }
    }
}
