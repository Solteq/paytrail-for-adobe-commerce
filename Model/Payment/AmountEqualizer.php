<?php

namespace Paytrail\PaymentService\Model\Payment;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Paytrail\SDK\Request\PaymentRequest;

class AmountEqualizer
{
    /**
     * @var DiscountGetterInterface[]
     */
    private $discountGetters;
    /**
     * @var \Magento\SalesRule\Model\DeltaPriceRound
     */
    private $deltaPriceRound;
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(
        \Magento\SalesRule\Model\DeltaPriceRound $deltaPriceRound,
        ScopeConfigInterface $scopeConfig,
        $discountGetters = []
    ) {
        $this->deltaPriceRound = $deltaPriceRound;
        $this->discountGetters = $discountGetters;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Equals request total amount and items amounts.
     *
     * @param PaymentRequest $paytrailPayment
     * @return PaymentRequest
     */
    public function equal(PaymentRequest $paytrailPayment)
    {
        $totalAmount = $paytrailPayment->getAmount();
        $summaryAmount = 0;

        foreach ($paytrailPayment->getItems() as $item) {
            $summaryAmount += $item->getUnitPrice();
        }

        if ($totalAmount === $summaryAmount) {
            return $paytrailPayment;
        }

        if ($summaryAmount > $totalAmount) {
            $equalAmount = $summaryAmount - $totalAmount;
            foreach ($paytrailPayment->getItems() as $item) {
                if ($item->getProductCode() === 'shipping-row') {
                    $item->setUnitPrice($item->getUnitPrice() - $equalAmount);
                }
            }
        }

        if ($totalAmount > $summaryAmount) {
            $equalAmount = $totalAmount - $summaryAmount;
            foreach ($paytrailPayment->getItems() as $item) {
                if ($item->getProductCode() === 'shipping-row') {
                    $item->setUnitPrice($item->getUnitPrice() + $equalAmount);
                }
            }
        }

        return $paytrailPayment;
    }
}
