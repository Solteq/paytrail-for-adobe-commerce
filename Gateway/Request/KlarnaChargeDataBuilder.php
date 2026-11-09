<?php

namespace Paytrail\PaymentService\Gateway\Request;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Paytrail\PaymentService\Model\Payment\PaymentDataProvider;
use Paytrail\SDK\Model\KlarnaProviderDetails;
use Paytrail\SDK\Model\ProviderDetails;
use Paytrail\SDK\Request\KlarnaPaymentRequest;

class KlarnaChargeDataBuilder implements BuilderInterface
{
    /**
     * KlarnaChargeDataBuilder constructor.
     *
     * @param KlarnaPaymentRequest $klarnaPaymentRequest
     * @param ProviderDetails $providerDetails
     * @param KlarnaProviderDetails $klarnaProviderDetails
     * @param PaymentDataProvider $paymentDataProvider
     */
    public function __construct(
        private readonly KlarnaPaymentRequest $klarnaPaymentRequest,
        private readonly ProviderDetails      $providerDetails,
        private readonly KlarnaProviderDetails $klarnaProviderDetails,
        private readonly PaymentDataProvider  $paymentDataProvider,
    ) {
    }

    /**
     * @inheritdoc
     *
     * @param array $buildSubject
     * @return array
     * @throws NoSuchEntityException
     */
    public function build(array $buildSubject): array
    {
        $klarnaPayment = $this->paymentDataProvider->setPaymentRequestData(
            $this->klarnaPaymentRequest,
            $buildSubject['order'],
            $buildSubject['payment_method']
        );

        $this->klarnaProviderDetails->setNetworkSessionToken($buildSubject['network_session_token']);
        $this->providerDetails->setKlarna($this->klarnaProviderDetails);
        $klarnaPayment->setProviderDetails($this->providerDetails);

        return [
            'order' => $buildSubject['order'],
            'request_data' => $klarnaPayment
        ];
    }
}
