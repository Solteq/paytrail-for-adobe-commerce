<?php

namespace Paytrail\PaymentService\Model\Refund;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
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
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        private RefundCallback $refundCallback,
        private LoggerInterface $log,
        private OrderRepositoryInterface $orderRepository
    ) {
    }

    /**
     * SetRefundRequestData function
     *
     * @param RefundRequest $paytrailRefund
     * @param float|string $amount
     * @param int|string $orderId
     * @return void
     * @throws CheckoutException
     */
    public function setRefundRequestData(RefundRequest $paytrailRefund, float|string $amount, int|string $orderId): void
    {
        if ($amount <= 0) {
            $message = 'Refund amount must be above 0';
            $this->log->logData(\Monolog\Logger::ERROR, $message);
            throw new CheckoutException(__($message));
        }

        $paytrailRefund->setAmount(round($amount * 100));
        $paytrailRefund->setEmail($this->getCustomerEmail($orderId));

        $callback = $this->refundCallback->createRefundCallback();
        $paytrailRefund->setCallbackUrls($callback);
    }

    /**
     * Get customer email.
     *
     * @param int|string $orderId
     * @return string
     */
    private function getCustomerEmail(int|string $orderId): string
    {
        $order = $this->orderRepository->get($orderId);

        return $order->getCustomerEmail();
    }
}
