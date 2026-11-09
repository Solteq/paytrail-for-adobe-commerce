<?php

namespace Paytrail\PaymentService\Model\Klarna;

use Magento\Quote\Model\Quote\Address;

/**
 * Applies Klarna Web SDK address payloads (partial, from shippingaddresschange, or full, from the
 * complete event's stateContext.shipping) onto a Magento quote address.
 */
class QuoteAddressUpdater
{
    /**
     * Apply a partial Klarna shipping address (city, postalCode, region, country only) to a quote address.
     *
     * @param Address $address
     * @param array $klarnaAddress
     * @return void
     */
    public function applyPartial(Address $address, array $klarnaAddress): void
    {
        $countryId = $klarnaAddress['country'] ?? $address->getCountryId();

        $address->setCity($klarnaAddress['city'] ?? $address->getCity());
        $address->setPostcode($klarnaAddress['postalCode'] ?? $address->getPostcode());
        $address->setCountryId($countryId);

        if (!empty($klarnaAddress['region'])) {
            $address->setRegionCode($this->normalizeRegion($klarnaAddress['region'], $countryId));
        }
    }

    /**
     * Apply a full Klarna address (as returned in stateContext.shipping after purchase approval) to a
     * quote address, including street lines and contact details.
     *
     * @param Address $address
     * @param array $klarnaAddress
     * @return void
     */
    public function applyFull(Address $address, array $klarnaAddress): void
    {
        $countryId = $klarnaAddress['country'] ?? $address->getCountryId();

        $street = array_values(array_filter([
            $klarnaAddress['streetAddress'] ?? null,
            $klarnaAddress['streetAddress2'] ?? null,
        ]));

        $address->setFirstname($klarnaAddress['givenName'] ?? $address->getFirstname());
        $address->setLastname($klarnaAddress['familyName'] ?? $address->getLastname());
        $address->setStreet($street ?: $address->getStreet());
        $address->setCity($klarnaAddress['city'] ?? $address->getCity());
        $address->setPostcode($klarnaAddress['postalCode'] ?? $address->getPostcode());
        $address->setCountryId($countryId);
        $address->setTelephone($klarnaAddress['phoneNumber'] ?? $klarnaAddress['phone'] ?? $address->getTelephone() ?: '-');

        if (!empty($klarnaAddress['email'])) {
            $address->setEmail($klarnaAddress['email']);
        }

        if (!empty($klarnaAddress['region'])) {
            $address->setRegionCode($this->normalizeRegion($klarnaAddress['region'], $countryId));
        }
    }

    /**
     * Klarna returns the region in ISO 3166-2 format (e.g. "US-OH"), Magento expects only the region
     * code part (e.g. "OH").
     *
     * @param string $region
     * @param string|null $countryId
     * @return string
     */
    private function normalizeRegion(string $region, ?string $countryId): string
    {
        $prefix = $countryId ? $countryId . '-' : '';

        if ($prefix && str_starts_with($region, $prefix)) {
            return substr($region, strlen($prefix));
        }

        return $region;
    }
}
