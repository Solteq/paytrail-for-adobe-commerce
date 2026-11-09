<?php

namespace Paytrail\PaymentService\Model\Klarna;

use Magento\Quote\Model\Quote\Address;

/**
 * Maps Magento shipping rates collected on a quote address into Klarna's shippingOptions format.
 */
class ShippingOptionsBuilder
{
    /**
     * Default Klarna shipping type used for all Magento shipping methods, since Magento has no
     * directly equivalent concept of shipping type.
     *
     * @see https://docs.klarna.com/acquirer/paytrail/express-checkout/web-integration/
     */
    private const DEFAULT_SHIPPING_TYPE = 'TO_DOOR';

    /**
     * Build Klarna shippingOptions list from the shipping rates already collected on the address.
     *
     * @param Address $shippingAddress
     * @return array
     */
    public function build(Address $shippingAddress): array
    {
        $shippingOptions = [];

        foreach ($shippingAddress->getAllShippingRates() as $rate) {
            if ($rate->getErrorMessage()) {
                continue;
            }

            $shippingOptions[] = [
                'shippingOptionReference' => $rate->getCode(),
                'displayName' => $this->buildDisplayName($rate),
                'description' => (string) ($rate->getMethodDescription() ?: $rate->getMethodTitle()),
                'amount' => (int) round((float) $rate->getPrice() * 100),
                'shippingType' => self::DEFAULT_SHIPPING_TYPE,
            ];
        }

        return $shippingOptions;
    }

    /**
     * Pick the cheapest shipping option reference from the built options, to preselect it for the customer.
     *
     * @param array $shippingOptions
     * @return string|null
     */
    public function getCheapestOptionReference(array $shippingOptions): ?string
    {
        $cheapest = null;

        foreach ($shippingOptions as $option) {
            if ($cheapest === null || $option['amount'] < $cheapest['amount']) {
                $cheapest = $option;
            }
        }

        return $cheapest['shippingOptionReference'] ?? null;
    }

    /**
     * Build a human-readable display name for the shipping rate.
     *
     * @param \Magento\Quote\Model\Quote\Address\Rate $rate
     * @return string
     */
    private function buildDisplayName($rate): string
    {
        $carrierTitle = (string) $rate->getCarrierTitle();
        $methodTitle = (string) $rate->getMethodTitle();

        return trim($carrierTitle . ' - ' . $methodTitle, ' -') ?: (string) __('Shipping');
    }
}
