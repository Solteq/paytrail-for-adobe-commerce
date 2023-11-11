<?php

namespace Paytrail\PaymentService\Model\Refund;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Model\RefundCallback;
use Paytrail\SDK\Request\RefundRequest;
use Psr\Log\LoggerInterface;

class RefundDataProvider
{
    /**
     * RefundDataProvider constructor.
     *
     * @param RefundCallback $refundCallback
     * @param LoggerInterface $log
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        private RefundCallback $refundCallback,
        private LoggerInterface $log,
        private CustomerRepositoryInterface $customerRepository
    ) {
    }

    /**
     * SetRefundRequestData function
     *
     * @param RefundRequest $paytrailRefund
     * @param float|string $amount
     * @param string $customerId
     * @return void
     * @throws CheckoutException
     */
    public function setRefundRequestData(RefundRequest $paytrailRefund, float|string $amount, string $customerId): void
    {
        if ($amount <= 0) {
            $message = 'Refund amount must be above 0';
            $this->log->logData(\Monolog\Logger::ERROR, $message);
            throw new CheckoutException(__($message));
        }

        $paytrailRefund->setAmount(round($amount * 100));
        $paytrailRefund->setEmail($this->getCustomerEmail($customerId));

        $callback = $this->refundCallback->createRefundCallback();
        $paytrailRefund->setCallbackUrls($callback);
    }

    /**
     * Get customer email.
     *
     * @param string $customerId
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getCustomerEmail(string $customerId): string
    {
        $customer = $this->customerRepository->getById((int)$customerId);

        return $customer->getEmail();
    }
}
