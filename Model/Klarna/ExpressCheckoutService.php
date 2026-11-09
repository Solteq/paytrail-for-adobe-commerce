<?php

namespace Paytrail\PaymentService\Model\Klarna;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Group as CustomerGroup;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\CommandManagerPoolInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Logger\PaytrailLogger;
use Paytrail\PaymentService\Model\PaymentMethod\OrderPaymentMethodData;
use Paytrail\PaymentService\Model\Receipt\ProcessService;
use Paytrail\SDK\Response\KlarnaPaymentResponse;

/**
 * Orchestrates the Klarna Express Checkout flow: dynamic shipping estimation on the quote, and
 * finalizing the purchase by placing the Magento order and charging it through Paytrail's
 * /payments/klarna/charge endpoint.
 */
class ExpressCheckoutService
{
    private const KLARNA_SUB_METHOD = 'klarna';

    /**
     * ExpressCheckoutService constructor.
     *
     * @param CheckoutSession $checkoutSession
     * @param CartRepositoryInterface $quoteRepository
     * @param CartManagementInterface $cartManagement
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderManagementInterface $orderManagement
     * @param CommandManagerPoolInterface $commandManagerPool
     * @param ProcessService $processService
     * @param OrderPaymentMethodData $orderPaymentMethodData
     * @param QuoteAddressUpdater $addressUpdater
     * @param ShippingOptionsBuilder $shippingOptionsBuilder
     * @param LineItemsBuilder $lineItemsBuilder
     * @param Config $gatewayConfig
     * @param PaytrailLogger $log
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly CartManagementInterface $cartManagement,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderManagementInterface $orderManagement,
        private readonly CommandManagerPoolInterface $commandManagerPool,
        private readonly ProcessService $processService,
        private readonly OrderPaymentMethodData $orderPaymentMethodData,
        private readonly QuoteAddressUpdater $addressUpdater,
        private readonly ShippingOptionsBuilder $shippingOptionsBuilder,
        private readonly LineItemsBuilder $lineItemsBuilder,
        private readonly Config $gatewayConfig,
        private readonly PaytrailLogger $log
    ) {
    }

    /**
     * Handle Klarna's "shippingaddresschange" event: collect shipping rates for the given (partial)
     * address and return the available shipping options with a preselected default.
     *
     * @param array $klarnaAddress
     * @return array
     * @throws LocalizedException
     */
    public function estimateShipping(array $klarnaAddress): array
    {
        $quote = $this->getQuote();
        $shippingAddress = $quote->getShippingAddress();

        $this->addressUpdater->applyPartial($shippingAddress, $klarnaAddress);
        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->collectShippingRates();

        $shippingOptions = $this->shippingOptionsBuilder->build($shippingAddress);
        $selectedReference = $this->shippingOptionsBuilder->getCheapestOptionReference($shippingOptions);

        if ($selectedReference) {
            $shippingAddress->setShippingMethod($selectedReference);
        }

        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
        $this->quoteRepository->save($quote);

        return [
            'amount' => $this->lineItemsBuilder->getAmount($quote),
            'lineItems' => $this->lineItemsBuilder->build($quote),
            'shippingOptions' => $shippingOptions,
            'selectedShippingOptionReference' => $selectedReference,
        ];
    }

    /**
     * Handle Klarna's "shippingoptionselect" event: apply the newly selected shipping option to the
     * quote and return the updated amount and line items.
     *
     * @param string $shippingOptionReference
     * @return array
     * @throws LocalizedException
     */
    public function selectShippingOption(string $shippingOptionReference): array
    {
        $quote = $this->getQuote();
        $shippingAddress = $quote->getShippingAddress();

        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->collectShippingRates();
        $shippingAddress->setShippingMethod($shippingOptionReference);

        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
        $this->quoteRepository->save($quote);

        return [
            'amount' => $this->lineItemsBuilder->getAmount($quote),
            'lineItems' => $this->lineItemsBuilder->build($quote),
        ];
    }

    /**
     * Handle Klarna's "complete" event: apply the full shipping address, place the Magento order and
     * charge it through Paytrail's Klarna conversion feature using the provided network session token.
     *
     * @param array $payload Expected keys: shippingAddress (array), networkSessionToken (string),
     *                        shippingOptionReference (string, optional).
     * @return array
     */
    public function completePurchase(array $payload): array
    {
        $order = null;

        try {
            $quote = $this->getQuote();

            $shippingAddress = $quote->getShippingAddress();
            $klarnaAddress = $payload['shippingAddress'] ?? [];
            $this->addressUpdater->applyFull($shippingAddress, $klarnaAddress);

            if (!empty($payload['shippingOptionReference'])) {
                $shippingAddress->setCollectShippingRates(true);
                $shippingAddress->collectShippingRates();
                $shippingAddress->setShippingMethod($payload['shippingOptionReference']);
            }

            $billingAddress = $quote->getBillingAddress();
            $this->addressUpdater->applyFull($billingAddress, $klarnaAddress);

            $email = $klarnaAddress['email'] ?? $quote->getCustomerEmail();
            if (!$quote->getCustomerId()) {
                $quote->setCheckoutMethod(CartManagementInterface::METHOD_GUEST);
                $quote->setCustomerId(null);
                $quote->setCustomerEmail($email);
                $quote->setCustomerIsGuest(true);
                $quote->setCustomerGroupId(CustomerGroup::NOT_LOGGED_IN_ID);
            }
            $billingAddress->setEmail($email);

            $quote->getPayment()->importData(['method' => Config::CODE]);
            $quote->setInventoryProcessed(false);
            $quote->collectTotals();
            $this->quoteRepository->save($quote);

            $orderId = $this->cartManagement->placeOrder($quote->getId());
            $order = $this->orderRepository->get($orderId);

            $this->orderPaymentMethodData->setSelectedPaymentMethodData($order, self::KLARNA_SUB_METHOD);

            return $this->chargeOrder($order, $payload['networkSessionToken'] ?? '');
        } catch (\Exception $e) {
            $this->log->error(\sprintf(
                'Klarna Express Checkout: failed to complete purchase: %s',
                $e->getMessage()
            ), $e->getTrace());

            $this->cancelOrder($order);

            return [
                'success' => false,
                'message' => __('Payment failed. Please try again or select another payment method.')->render(),
            ];
        }
    }

    /**
     * Execute the Paytrail Klarna charge gateway command for the placed order and handle its response.
     *
     * @param Order $order
     * @param string $networkSessionToken
     * @return array
     */
    private function chargeOrder(Order $order, string $networkSessionToken): array
    {
        $commandExecutor = $this->commandManagerPool->get('paytrail');
        $response = $commandExecutor->executeByCode(
            'klarna_express_charge',
            null,
            [
                'order' => $order,
                'payment_method' => self::KLARNA_SUB_METHOD,
                'network_session_token' => $networkSessionToken,
            ]
        );

        if ($response['error']) {
            $this->cancelOrder($order);

            return [
                'success' => false,
                'message' => __('Payment failed. Please try again or select another payment method.')->render(),
            ];
        }

        /** @var KlarnaPaymentResponse $klarnaResponse */
        $klarnaResponse = $response['data'];

        if ($klarnaResponse->getStepUpUrl()) {
            // Order stays in pending_payment, the existing receipt/callback controllers will finalize
            // it once the customer returns from Klarna's step-up (3DS-like) verification.
            return [
                'success' => true,
                'stepUpUrl' => $klarnaResponse->getStepUpUrl(),
            ];
        }

        if (!$klarnaResponse->getTransactionId()) {
            $this->cancelOrder($order);

            return [
                'success' => false,
                'message' => __('Payment failed. Please try again or select another payment method.')->render(),
            ];
        }

        $this->finalizeOrder($order, $klarnaResponse->getTransactionId());

        return [
            'success' => true,
            'redirectUrl' => 'checkout/onepage/success',
        ];
    }

    /**
     * Finalize the order synchronously after a successful Klarna charge: record the transaction,
     * generate the invoice and set the order to processing.
     *
     * @param Order $order
     * @param string $transactionId
     * @return void
     */
    private function finalizeOrder(Order $order, string $transactionId): void
    {
        $details = [
            'orderNo' => $order->getIncrementId(),
            'method' => self::KLARNA_SUB_METHOD,
            'api_status' => Config::PAYTRAIL_API_PAYMENT_STATUS_OK,
        ];

        $this->processService->processPayment($order, $transactionId, $details);
        $this->processService->processInvoice($order);
        $this->processService->processOrder(Config::PAYTRAIL_API_PAYMENT_STATUS_OK, $order);

        $quote = $this->checkoutSession->getQuote();
        $quote->setIsActive(false);
        $this->quoteRepository->save($quote);
    }

    /**
     * Cancel the order and restore the quote, mirroring the existing redirect controller's error handling.
     *
     * @param Order|null $order
     * @return void
     */
    private function cancelOrder(?Order $order): void
    {
        if ($order && $order->getId()) {
            try {
                $this->orderManagement->cancel($order->getId());
                $order->addCommentToStatusHistory(
                    __('Order canceled. Failed to charge Klarna Express Checkout payment.')
                );
                $this->orderRepository->save($order);
            } catch (\Exception $e) {
                $this->log->error(\sprintf('Klarna Express Checkout: failed to cancel order: %s', $e->getMessage()));
            }
        }

        $this->checkoutSession->restoreQuote();
    }

    /**
     * Get the active quote from the checkout session.
     *
     * @return Quote
     */
    private function getQuote(): Quote
    {
        return $this->checkoutSession->getQuote();
    }
}
