<?php

namespace Paytrail\PaymentService\Model\ApplePay;

use Magento\Framework\HTTP\Header;
use Magento\Framework\View\Asset\Repository;
use Magento\Sales\Model\Order;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Model\FinnishReferenceNumber;
use Paytrail\PaymentService\Model\Payment\PaymentDataProvider;
use Paytrail\SDK\Model\Provider;

class ApplePayDataProvider
{
    /**
     * ApplePayDataProvider constructor.
     *
     * @param Config $gatewayConfig
     * @param Repository $assetRepository
     * @param PaymentDataProvider $paymentDataProvider
     * @param FinnishReferenceNumber $referenceNumber
     * @param Header $httpHeader
     */
    public function __construct(
        private Config     $gatewayConfig,
        private Repository $assetRepository,
        private PaymentDataProvider $paymentDataProvider,
        private FinnishReferenceNumber $referenceNumber,
        private Header $httpHeader
    ) {
    }

    /**
     * Returns true if browser is Safari and Apple Pay is enabled.
     *
     * @return bool
     */
    public function canApplePay(): bool
    {
        if ($this->isSafariBrowser() && $this->gatewayConfig->isApplePayEnabled()) {
            return true;
        }

        return false;
    }

    /**
     * Adds Apple Pay method data into payment methods groups.
     *
     * @param array $groupMethods
     * @return array
     */
    public function addApplePayPaymentMethod(array $groupMethods): array
    {
        if ($this->isApplePayAdded($groupMethods)) {
            foreach ($groupMethods as $key => $method) {
                if ($method['id'] === 'mobile') {
                    $groupMethods[$key]['providers'][] = $this->getApplePayProviderData();
                }
            }
        }

        return $groupMethods;
    }

    /**
     * Get params for processing order and payment.
     *
     * @param array $params
     * @param Order $order
     * @return array
     * @throws \Paytrail\PaymentService\Exceptions\CheckoutException
     */
    public function getApplePayFailParams($params, $order): array
    {
        $paramsToProcess = [
            'checkout-transaction-id' => '',
            'checkout-account' => '',
            'checkout-method' => '',
            'checkout-algorithm' => '',
            'checkout-timestamp' => '',
            'checkout-nonce' => '',
            'checkout-reference' => $this->referenceNumber->getReference($order),
            'checkout-provider' => Config::APPLE_PAY_PAYMENT_CODE,
            'checkout-status' => Config::PAYTRAIL_API_PAYMENT_STATUS_FAIL,
            'checkout-stamp' => $this->paymentDataProvider->getStamp($order),
            'signature' => '',
            'skip_validation' => 1
        ];

        foreach ($params as $param) {
            if (array_key_exists($param['name'], $paramsToProcess)) {
                $paramsToProcess[$param['name']] = $param['value'];
            }
        }

        return $paramsToProcess;
    }

    /**
     * Get Apple Pay provider data for payment render.
     *
     * @return Provider
     */
    private function getApplePayProviderData(): Provider
    {
        $applePayProvider = new Provider();

        $applePayProvider
            ->setId('applepay')
            ->setGroup('mobile')
            ->setUrl(null)
            ->setIcon($this->assetRepository->getUrl('Paytrail_PaymentService::images/apple-pay-logo.png'))
            ->setName('ApplePay')
            ->setParameters(null)
            ->setSvg($this->assetRepository->getUrl('Paytrail_PaymentService::images/apple-pay-logo.svg'));

        return $applePayProvider;
    }

    /**
     * Returns if user browser is Safari.
     *
     * @return bool
     */
    private function isSafariBrowser(): bool
    {
        $user_agent = $this->httpHeader->getHttpUserAgent();

        if (stripos($user_agent, 'Chrome') !== false && stripos($user_agent, 'Safari') !== false) {
            return false;
        } elseif (stripos($user_agent, 'Safari') !== false) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns if Apple Pay method is already added to payment methods by Paytrail API.
     *
     * @param $groupMethods
     * @return bool
     */
    private function isApplePayAdded($groupMethods): bool
    {
        foreach($groupMethods as $method) {
            if ($method['id'] === 'mobile') {
                foreach($method['providers'] as $provider) {
                    if ($provider->getId() === 'apple-pay') {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
