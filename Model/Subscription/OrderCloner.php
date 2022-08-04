<?php

namespace Paytrail\PaymentService\Model\Subscription;

use Magento\Backend\Model\Session\Quote;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Magento\Sales\Model\AdminOrder\Create;
use Magento\Sales\Model\Order\Reorder\UnavailableProductsProvider;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Vault\Model\PaymentTokenManagement;
use Psr\Log\LoggerInterface;
use Magento\Quote\Api\CartRepositoryInterface;

class OrderCloner
{
    /**
     * @var CollectionFactory
     */
    private $orderCollection;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var UnavailableProductsProvider
     */
    private $unavailableProducts;
    /**
     * @var Quote
     */
    private $quoteSession;
    /**
     * @var QuoteManagement
     */
    private $quoteManagement;
    /**
     * @var JoinProcessorInterface
     */
    private $joinProcessor;
    /**
     * @var PaymentTokenManagement
     */
    private $paymentTokenManagement;
    /**
     * @var SerializerInterface
     */
    private $serializer;
    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    private $cartRepositoryInterface;

    /**
     * @var OrderItemRepositoryInterface
     */
    private $orderItemRepositoryInterface;

    /**
     * @param CollectionFactory $orderCollection
     * @param UnavailableProductsProvider $unavailableProducts
     * @param Quote $quoteSession
     * @param JoinProcessorInterface $joinProcessor
     * @param QuoteManagement $quoteManagement
     * @param LoggerInterface $logger
     * @param PaymentTokenManagement $paymentTokenManagement
     * @param SerializerInterface $serializer
     * @param CartRepositoryInterface $cartRepositoryInterface
     * @param OrderItemRepositoryInterface $orderItemRepositoryInterface
     * @param ProductRepositoryInterface $productRepositoryInterface
     */
    public function __construct(
        CollectionFactory $orderCollection,
        UnavailableProductsProvider $unavailableProducts,
        Quote $quoteSession,
        JoinProcessorInterface $joinProcessor,
        QuoteManagement $quoteManagement,
        LoggerInterface $logger,
        PaymentTokenManagement $paymentTokenManagement,
        SerializerInterface  $serializer,
        CartRepositoryInterface $cartRepositoryInterface,
        OrderItemRepositoryInterface $orderItemRepositoryInterface,
        ProductRepositoryInterface $productRepositoryInterface
    ) {
        $this->orderCollection = $orderCollection;
        $this->unavailableProducts = $unavailableProducts;
        $this->quoteSession = $quoteSession;
        $this->joinProcessor = $joinProcessor;
        $this->quoteManagement = $quoteManagement;
        $this->logger = $logger;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->serializer = $serializer;
        $this->cartRepositoryInterface = $cartRepositoryInterface;
        $this->orderItemRepositoryInterface = $orderItemRepositoryInterface;
        $this->productRepositoryInterface = $productRepositoryInterface;
    }

    /**
     * Clones orders by existing order ids, if performance becomes an issue. Consider limiting results from
     * @see \Paytrail\PaymentService\Model\ResourceModel\Subscription::getClonableOrderIds
     *
     * @param int[] $orderIds
     * @return \Magento\Sales\Model\Order[]
     */
    public function cloneOrders($orderIds): array
    {
        if (empty($orderIds)) {
            return [];
        }

        $orderCollection = $this->orderCollection->create();
        $orderCollection->addFieldToFilter('entity_id', $orderIds);
        $this->joinProcessor->process($orderCollection);
        $newOrders = [];

        /** @var \Magento\Sales\Model\Order $order */
        foreach ($orderCollection as $order) {
            try {
                $clonedOrder = $this->clone($order);
                $newOrders[$clonedOrder->getId()] = $clonedOrder;
            } catch (LocalizedException $exception) {
                $this->logger->error(__(
                    'Recurring payment order cloning error: %error',
                    ['error' => $exception->getMessage()]
                ));
                continue;
            }
        }

        return $newOrders;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface|\Magento\Sales\Model\Order $oldOrder
     * @throws LocalizedException
     */
    private function clone(
        \Magento\Sales\Api\Data\OrderInterface $oldOrder
    ) {
        $this->validateOrder($oldOrder);

        $this->quoteSession->clearStorage();
        $this->quoteSession->setData('use_old_shipping_method', true);
        $oldOrder->setData('reordered', true);

        $quote = $this->getQuote($oldOrder);

        // adding original_price to item quote
        $oldItemIds = array_keys($oldOrder->getItems());
        $i = 0;
        foreach ($quote->getItemsCollection()->getItems() as $item) {
            $item->setData('original_price', $this->orderItemRepositoryInterface->get($oldItemIds[$i])->getOriginalPrice());
            $i++;
        }

        return $this->quoteManagement->submit($quote);
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     */
    private function validateOrder($order)
    {
        if ($order->canReorder()
            && count($this->unavailableProducts->getForOrder($order)) == 0
            && $order->getStatus() === 'processing'
        ) {
            return true;
        }

        throw new LocalizedException(__(
            'Order id: %id cannot be reordered',
            ['id' => $order->getId()]
        ));
    }

    /**
     * @param \Magento\Sales\Model\Order$oldOrder
     * @return \Magento\Quote\Model\Quote
     * @throws LocalizedException
     */
    private function getQuote(\Magento\Sales\Api\Data\OrderInterface $oldOrder): \Magento\Quote\Model\Quote
    {
        $quote = $this->cartRepositoryInterface->get($oldOrder->getQuoteId());
        $quote->setData('recurring_payment_flag', true);
        $quote->getPayment()->setMethod(
            $this->getQuotePaymentMethod($oldOrder)
        );

        return $quote;
    }

    /**
     * @param $oldOrder
     * @return string
     */
    private function getQuotePaymentMethod($oldOrder): string
    {
        $payment = $this->paymentTokenManagement->getByPaymentId($oldOrder->getId());
        if ($payment) {
            $token = $this->serializer->unserialize($payment->getTokenDetails());
            return 'Card: **** **** **** ' . $token['maskedCC'];
        }

        return $oldOrder->getPayment()->getMethod();
    }
}
