<?php

namespace Paytrail\PaymentService\Model\Subscription;

use Exception;
use Magento\Backend\Model\Session\Quote;
use Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Reorder\UnavailableProductsProvider;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Psr\Log\LoggerInterface;
use Magento\Quote\Api\CartRepositoryInterface;

class OrderCloner
{
    /**
     * @param CollectionFactory $orderCollection
     * @param UnavailableProductsProvider $unavailableProducts
     * @param Quote $quoteSession
     * @param JoinProcessorInterface $joinProcessor
     * @param QuoteManagement $quoteManagement
     * @param LoggerInterface $logger
     * @param CartRepositoryInterface $cartRepositoryInterface
     */
    public function __construct(
        private readonly CollectionFactory $orderCollection,
        private readonly UnavailableProductsProvider $unavailableProducts,
        private readonly Quote $quoteSession,
        private readonly JoinProcessorInterface $joinProcessor,
        private readonly QuoteManagement $quoteManagement,
        private readonly LoggerInterface $logger,
        private readonly CartRepositoryInterface $cartRepositoryInterface
    ) {
    }

    /**
     * Clones orders by existing order ids if performance becomes an issue. Consider limiting results from
     *
     * @param int[] $orderIds
     *
     * @return Order[]
     * @see \Paytrail\PaymentService\Model\ResourceModel\Subscription::getClonableOrderIds
     *
     */
    public function cloneOrders(array $orderIds): array
    {
        if (empty($orderIds)) {
            return [];
        }

        $orderCollection = $this->orderCollection->create();
        $orderCollection->addFieldToFilter('entity_id', $orderIds);
        $this->joinProcessor->process($orderCollection);
        $newOrders = [];

        /** @var Order $order */
        foreach ($orderCollection as $order) {
            try {
                $clonedOrder = $this->clone($order);
                $newOrders[$order->getEntityId()] = $clonedOrder;
            } catch (Exception $exception) {
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
     * @param OrderInterface $oldOrder
     *
     * @return AbstractExtensibleModel|OrderInterface|object|null
     * @throws LocalizedException
     */
    private function clone(OrderInterface $oldOrder)
    {
        $this->validateOrder($oldOrder);

        $this->quoteSession->clearStorage();
        $this->quoteSession->setData('use_old_shipping_method', true);
        $oldOrder->setData('reordered', true);

        $quote = $this->getQuote($oldOrder);

        $this->removeNonScheduledProducts($quote);

        return $this->quoteManagement->submit($quote);
    }

    /**
     * @param $quote
     *
     * @return void
     */
    private function removeNonScheduledProducts($quote): void
    {
        foreach ($quote->getAllVisibleItems() as $quoteItem) {
            if (!$quoteItem->getProduct()->getRecurringPaymentSchedule()) {
                $quote->deleteItem($quoteItem);
                $quote->setTotalsCollectedFlag(false);
            }
        }

        $quote->save();
        $quote->collectTotals();
    }

    /**
     * @param Order $order
     *
     *@throws LocalizedException
*/
    private function validateOrder($order)
    {
        if ($order->canReorder()
            && count($this->unavailableProducts->getForOrder($order)) == 0
        ) {
            return true;
        }

        throw new LocalizedException(__(
            'Order id: %id cannot be reordered',
            ['id' => $order->getId()]
        ));
    }

    /**
     * @param Order $oldOrder
     *
     * @return \Magento\Quote\Model\Quote
     * @throws LocalizedException
     */
    private function getQuote(OrderInterface $oldOrder): \Magento\Quote\Model\Quote
    {
        $quote = $this->cartRepositoryInterface->get($oldOrder->getQuoteId());
        $quote->setData('recurring_payment_flag', true);

        return $quote;
    }
}
