<?php

namespace Paytrail\PaymentService\Gateway\Request;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Model\Receipt\ProcessService;
use Paytrail\PaymentService\Model\RefundCallback;
use Paytrail\SDK\Request\RefundRequest;
use Psr\Log\LoggerInterface;

class RefundDataBuilder implements BuilderInterface
{
    /**
     * RefundDataBuilder constructor.
     *
     * @param ProcessService $processService
     * @param SubjectReader $subjectReader
     * @param LoggerInterface $log
     * @param RefundRequest $refundRequest
     * @param RefundCallback $refundCallback
     */
    public function __construct(
        private ProcessService $processService,
        private readonly SubjectReader $subjectReader,
        private readonly LoggerInterface $log,
        private readonly RefundRequest $refundRequest,
        private readonly RefundCallback $refundCallback
    ) {
    }

    /**
     * Build request
     *
     * @param array $buildSubject
     *
     * @return array
     * @throws LocalizedException
     */
    public function build(array $buildSubject): array
    {
        $paymentDataObject = $this->subjectReader->readPayment($buildSubject);
        $amount            = $this->subjectReader->readAmount($buildSubject);

        $order      = $paymentDataObject->getOrder();
        $orderItems = $order->getItems();
        $payment    = $paymentDataObject->getPayment();

        $errMsg = null;

        if ($amount <= 0) {
            $errMsg = 'Invalid amount for refund.';
        }

        if (!$payment->getTransactionId()) {
            $errMsg = 'Invalid transaction ID.';
        }

        if (count($this->getTaxRates($orderItems)) !== 1) {
            $errMsg = 'Cannot refund order with multiple tax rates. Please refund offline.';
        }

        if (isset($errMsg)) {
            $this->log->error($errMsg);
            $this->processService->processError($errMsg);
        }

        // Handle request
        $paytrailRefund = $this->refundRequest;
        $this->setRefundRequestData($paytrailRefund, $amount);

        return [
            'payment'               => $payment,
            'transaction_id'        => $payment->getTransactionId(),
            'parent_transaction_id' => $payment->getParentTransactionId(),
            'order'                 => $order,
            'refund_request'        => $paytrailRefund,
        ];
    }

    /**
     * SetRefundRequestData function
     *
     * @param RefundRequest $paytrailRefund
     * @param float         $amount
     *
     * @throws CheckoutException
     */
    private function setRefundRequestData(RefundRequest $paytrailRefund, float $amount): void
    {
        if ($amount <= 0) {
            $message = 'Refund amount must be above 0';
            $this->log->logData(\Monolog\Logger::ERROR, $message);
            throw new CheckoutException(__($message));
        }

        $paytrailRefund->setAmount(round($amount * 100));

        $callback = $this->refundCallback->createRefundCallback();
        $paytrailRefund->setCallbackUrls($callback);
    }

    /**
     * Get unique tax rates from order items
     *
     * @param array $items
     *
     * @return array
     */
    private function getTaxRates(array $items): array
    {
        $rates = [];
        foreach ($items as $item) {
            if ($item['price'] > 0) {
                $rates[] = round($item['vat'] * 100);
            }
        }

        return array_unique($rates, SORT_NUMERIC);
    }
}
