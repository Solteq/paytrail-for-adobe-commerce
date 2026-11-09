<?php

namespace Paytrail\PaymentService\ViewModel;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Paytrail\PaymentService\Gateway\Config\Config;

/**
 * View model exposing Klarna Express Checkout configuration to the frontend template.
 */
class KlarnaExpressCheckout implements ArgumentInterface
{
    /**
     * KlarnaExpressCheckout constructor.
     *
     * @param Config $gatewayConfig
     * @param CheckoutSession $checkoutSession
     * @param ResolverInterface $localeResolver
     */
    public function __construct(
        private readonly Config $gatewayConfig,
        private readonly CheckoutSession $checkoutSession,
        private readonly ResolverInterface $localeResolver
    ) {
    }

    /**
     * Whether the Klarna Express Checkout button should be displayed.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->gatewayConfig->isKlarnaExpressCheckoutEnabled()
            && (bool) $this->gatewayConfig->getKlarnaExpressClientId()
            && !$this->checkoutSession->getQuote()->isVirtual()
            && (float) $this->checkoutSession->getQuote()->getGrandTotal() > 0;
    }

    /**
     * Get the Klarna client id used to initialize the Web SDK.
     *
     * @return string
     */
    public function getClientId(): string
    {
        return $this->gatewayConfig->getKlarnaExpressClientId();
    }

    /**
     * Get the quote currency code.
     *
     * @return string
     */
    public function getCurrency(): string
    {
        return (string) $this->checkoutSession->getQuote()->getQuoteCurrencyCode();
    }

    /**
     * Get the total amount of the quote in minor currency units (cents).
     *
     * @return int
     */
    public function getAmount(): int
    {
        return (int) round((float) $this->checkoutSession->getQuote()->getGrandTotal() * 100);
    }

    /**
     * Get the locale used by the Klarna Web SDK, e.g. "fi-FI".
     *
     * @return string
     */
    public function getLocale(): string
    {
        return str_replace('_', '-', $this->localeResolver->getLocale());
    }
}
