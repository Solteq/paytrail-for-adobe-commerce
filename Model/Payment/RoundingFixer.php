<?php

namespace Paytrail\PaymentService\Model\Payment;

use Magento\Sales\Model\Order\Item;

class RoundingFixer
{
    /**
     * Correct rounding errors
     *
     * Adds a new item to the items array to correct rounding errors
     *
     * @param Item[] $items
     * @param float $discountedTotal
     * @param float $itemDiscountedTotal
     */
    public function correctRoundingErrors(array &$items, float $discountedTotal, float $itemDiscountedTotal): void
    {
        $delta = bcsub($discountedTotal, $itemDiscountedTotal, 2);
        if ($delta == 0) {
            return;
        }

        $items[] = [
            'title'  => 'Rounding correction',
            'code'   => 'rounding-correction',
            'price'  => $delta,
            'amount' => 1,
            'vat'    => 0,
            'stamp'  => 'rounding-correction_' . $items[0]->getOrderId(),
        ];
    }
}
