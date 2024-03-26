<?php

namespace Paytrail\PaymentService\Model\Receipt;

use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Service\InvoiceService;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Exceptions\TransactionSuccessException;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Logger\PaytrailLogger;
use Paytrail\PaymentService\Setup\Patch\Data\InstallPaytrail;
use Psr\Log\LoggerInterface;

class ProcessService
{
    private const CONTINUABLE_STATUSES = [
        Config::PAYTRAIL_API_PAYMENT_STATUS_PENDING,
        Config::PAYTRAIL_API_PAYMENT_STATUS_DELAYED,
    ];

    /**
     * ProcessService constructor.
     *
     * @param Config $gatewayConfig
     * @param OrderRepositoryInterface $orderRepositoryInterface
     * @param OrderSender $orderSender
     * @param LoggerInterface $logger
     * @param InvoiceService $invoiceService
     * @param Payment $currentOrderPayment
     * @param TransactionFactory $transactionFactory
     * @param LoadService $loadService
     * @param PaymentTransaction $paymentTransaction
     * @param CancelOrderService $cancelOrderService
     * @param PaytrailLogger $paytrailLogger
     * @param TransactionRepositoryInterface $transactionRepository
     */
    public function __construct(
        private Config                   $gatewayConfig,
        private OrderRepositoryInterface $orderRepositoryInterface,
        private OrderSender              $orderSender,
        private LoggerInterface          $logger,
        private InvoiceService           $invoiceService,
        private Payment                  $currentOrderPayment,
        private TransactionFactory       $transactionFactory,
        private LoadService $loadService,
        private PaymentTransaction $paymentTransaction,
        private CancelOrderService $cancelOrderService,
        private PaytrailLogger $paytrailLogger,
        private TransactionRepositoryInterface $transactionRepository
    ) {
    }

    /**
     * ProcessOrder function
     *
     * @param string $paymentVerified
     * @param Order $currentOrder
     * @return $this
     */
    public function processOrder($paymentVerified, $currentOrder)
    {
        $orderState = $this->gatewayConfig->getDefaultOrderStatus();

        if ($paymentVerified === 'ok') {
            $currentOrder->setState($orderState)->setStatus($orderState);
            $currentOrder->addCommentToStatusHistory(__('Payment has been completed'));
        } else {
            $currentOrder->setState(InstallPaytrail::ORDER_STATE_CUSTOM_CODE);
            $currentOrder->setStatus(InstallPaytrail::ORDER_STATUS_CUSTOM_CODE);
            $currentOrder->addCommentToStatusHistory(__('Pending payment from Paytrail Payment Service'));
        }

        $this->orderRepositoryInterface->save($currentOrder);

        try {
            $this->orderSender->send($currentOrder);
        } catch (\Exception $e) {
            $this->logger->error(\sprintf(
                'Paytrail: Order email sending failed: %s',
                $e->getMessage()
            ));
        }

        return $this;
    }

    /**
     * ProcessInvoice function
     *
     * @param Order $currentOrder
     * @return void
     * @throws \Paytrail\PaymentService\Exceptions\CheckoutException
     */
    public function processInvoice($currentOrder)
    {
        if ($currentOrder->canInvoice()) {
            try {
                /** @var /Magento/Sales/Api/Data/InvoiceInterface|/Magento/Sales/Model/Order/Invoice $invoice */
                $invoice = $this->invoiceService->prepareInvoice($currentOrder);
                //TODO: catch \InvalidArgumentException which extends \Exception
                $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
                $invoice->setTransactionId($this->currentOrderPayment->getLastTransId());
                $invoice->register();
                /** @var /Magento/Framework/DB/Transaction $transactionSave */
                $transactionSave = $this->transactionFactory->create();
                $transactionSave->addObject(
                    $invoice
                )->addObject(
                    $currentOrder
                )->save();
            } catch (\Exception $exception) {
                $this->processError($exception->getMessage());
            }
        }
    }

    /**
     * ProcessPayment function.
     *
     * @param Order $currentOrder
     * @param string $transactionId
     * @param array $details
     * @return void
     */
    public function processPayment($currentOrder, $transactionId, $details)
    {
        $transaction = $this->paymentTransaction->addPaymentTransaction($currentOrder, $transactionId, $details);

        $this->currentOrderPayment->setOrder($currentOrder);
        $this->currentOrderPayment->addTransactionCommentsToOrder($transaction, '');
        $this->currentOrderPayment->setLastTransId($transactionId);

        if ($currentOrder->getStatus() == 'canceled') {
            $this->cancelOrderService->notifyCanceledOrder($currentOrder);
        }
    }

    /**
     * ProcessExistingTransaction function
     *
     * @param Transaction $transaction
     * @return void
     * @throws \Paytrail\PaymentService\Exceptions\TransactionSuccessException
     */
    protected function processExistingTransaction($transaction)
    {
        $details = $transaction->getAdditionalInformation(Transaction::RAW_DETAILS);
        if (is_array($details)) {
            $this->processSuccess();
        }
    }

    /**
     * ProcessTransaction function.
     *
     * @param string $paymentStatus
     * @param string $transactionId
     * @param Order $currentOrder
     * @param string|int $orderId
     * @param array $paymentDetails
     * @return void
     * @throws CheckoutException
     * @throws TransactionSuccessException
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\MailException
     */
    public function processTransaction($paymentStatus, $transactionId, $currentOrder, $orderId, $paymentDetails)
    {
        $oldTransaction = $this->loadService->loadTransaction($transactionId, $currentOrder, $orderId);
        $this->validateOldTransaction($oldTransaction, $transactionId);
        $oldStatus = false;
        $paymentDetails['api_status'] = $paymentStatus;

        if ($oldTransaction) {
            // Backwards compatibility: If transaction exists without api_status, assume OK status since
            // only 'ok' status could create transactions in old version.
            $oldStatus = isset($oldTransaction->getAdditionalInformation(Transaction::RAW_DETAILS)['api_status'])
                ? $oldTransaction->getAdditionalInformation(Transaction::RAW_DETAILS)['api_status']
                : 'ok';

            $transaction = $this->updateOldTransaction($oldTransaction, $paymentDetails);
        } else {
            $transaction = $this->paymentTransaction->addPaymentTransaction(
                $currentOrder,
                $transactionId,
                $paymentDetails
            );
        }

        // Only append transaction comments to orders if the payment status changes
        if ($oldStatus !== $paymentStatus) {
            $this->currentOrderPayment->setOrder($currentOrder);
            $this->currentOrderPayment->addTransactionCommentsToOrder(
                $transaction,
                __('Paytrail Api - New payment status: "%status"', ['status' => $paymentStatus])
            );
            $this->currentOrderPayment->setLastTransId($transactionId);
        }

        if ($currentOrder->getStatus() == 'canceled') {
            $this->cancelOrderService->notifyCanceledOrder($orderId);
        }
    }

    /**
     * Validates ongoing transaction against the information in previous transaction
     *
     * Validate transaction id is the same as old id and that previous transaction did not finish the payment
     * Note that for backwards compatibility if transaction is missing api_status field, assume completed payment.
     *
     * @param \Magento\Sales\Model\Order\Payment\Transaction|bool $transaction
     * @param string $transactionId
     *
     * @throws \Paytrail\PaymentService\Exceptions\TransactionSuccessException thrown if previous transaction got "ok"
     * @throws CheckoutException thrown if multiple transaction ids are present.
     */
    protected function validateOldTransaction($transaction, $transactionId)
    {
        if ($transaction) {
            if ($transaction->getTxnId() !== $transactionId) {
                $this->processError('Payment failed, multiple transactions detected');
            }

            $details = $transaction->getAdditionalInformation(Transaction::RAW_DETAILS);
            if (isset($details['api_status']) && in_array($details['api_status'], self::CONTINUABLE_STATUSES)) {
                return;
            }

            // transaction was already completed with 'Ok' status.
            $this->processSuccess();
        }
    }

    /**
     * Update old transaction.
     *
     * @param bool|Transaction $oldTransaction
     * @param array $paymentDetails
     * @return Transaction
     * @throws LocalizedException
     */
    private function updateOldTransaction(bool|Transaction $oldTransaction, array $paymentDetails): Transaction
    {
        $transaction = $oldTransaction->setAdditionalInformation(Transaction::RAW_DETAILS, $paymentDetails);
        $this->transactionRepository->save($transaction);

        return $transaction;
    }

    /**
     * Process error
     *
     * @param string $errorMessage
     *
     * @throws CheckoutException
     */
    public function processError($errorMessage)
    {
        $this->paytrailLogger->logData(\Monolog\Logger::ERROR, $errorMessage);
        throw new CheckoutException(__($errorMessage));
    }

    /**
     * Process success
     *
     * @throws TransactionSuccessException
     */
    public function processSuccess(): void
    {
        throw new TransactionSuccessException(__('Success'));
    }
}
