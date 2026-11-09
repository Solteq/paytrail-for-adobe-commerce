<?php

namespace Paytrail\PaymentService\Model\Klarna;

use Magento\Quote\Model\Quote;

/**
 * Builds Klarna Web SDK compatible line item arrays (name/quantity/totalAmount) from a Magento quote.
 *
 * These line items are only used in responses sent back to the Klarna Web SDK running in the browser
 * (shippingaddresschange / shippingoptionselect events), they are not sent to Paytrail.
 */
class LineItemsBuilder
{
    /**
     * Build Klarna line items (products + shipping, if any) from the quote.
     *
     * @param Quote $quote
     * @return array
     */
    public function build(Quote $quote): array
    {
        $lineItems = [];

        foreach ($quote->getAllVisibleItems() as $item) {
            $lineItems[] = [
                'name' => $item->getName(),
                'quantity' => (int) $item->getQty(),
                'totalAmount' => (int) round($item->getRowTotalInclTax() * 100),
            ];
        }

        $shippingAddress = $quote->getShippingAddress();
        if (!$quote->isVirtual() && $shippingAddress && (float) $shippingAddress->getShippingInclTax() > 0) {
            $lineItems[] = [
                'name' => $shippingAddress->getShippingDescription() ?: (string) __('Shipping'),
                'quantity' => 1,
                'totalAmount' => (int) round($shippingAddress->getShippingInclTax() * 100),
            ];
        }

        return $lineItems;
    }

    /**
     * Get the total amount of the quote in non-negative minor currency units (cents).
     *
     * @param Quote $quote
     * @return int
     */
    public function getAmount(Quote $quote): int
    {
        return (int) round($quote->getGrandTotal() * 100);
    }
}
