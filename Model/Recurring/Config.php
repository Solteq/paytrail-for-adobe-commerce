<?php

namespace Paytrail\PaymentService\Model\Recurring;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const IS_RECURRING_PAYMENT_ENABLED     = 'sales/recurring_payment/active_recurring_payment';
    private const CONFIG_ORDER_CREATION_LEAD_DAYS  = 'sales/recurring_payment/warning_period';
    public const  DEFAULT_ORDER_CREATION_LEAD_DAYS = 7;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
    ) {
    }

    /**
     * Number of days ahead of the next order date that recurring orders are cloned for upcoming billing.
     *
     * Shares the "Recurring order lead time" (warning_period) setting so the customer notification
     * and the actual clone-to-billing gap always stay in sync.
     *
     * @return int
     */
    public function getOrderCreationLeadDays(): int
    {
        $value = $this->scopeConfig->getValue(
            self::CONFIG_ORDER_CREATION_LEAD_DAYS,
            ScopeInterface::SCOPE_STORE
        );

        return $value === null ? self::DEFAULT_ORDER_CREATION_LEAD_DAYS : (int)$value;
    }

    /**
     * Is recurring payment feature enable.
     *
     * @return bool
     */
    public function isRecurringPaymentEnabled(): bool
    {
        return (bool)$this->scopeConfig->getValue(
            self::IS_RECURRING_PAYMENT_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }
}
