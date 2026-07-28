<?php

namespace Paytrail\PaymentService\Model\Subscription;

use Magento\Sales\Model\Order\Config;
use Paytrail\PaymentService\Model\ResourceModel\Subscription\SubscriptionLink\Collection;
use Paytrail\PaymentService\Model\ResourceModel\Subscription\SubscriptionLink\CollectionFactory;

class ActiveOrderProvider
{

    /**
     * @param CollectionFactory $linkCollectionFactory
     * @param Config $orderConfig
     */
    public function __construct(
        private readonly CollectionFactory $linkCollectionFactory,
        private readonly Config $orderConfig
    ) {
    }

    /**
     * @return int[]
     */
    public function getPayableOrderIds(): array
    {
        return $this->getSubscriptionLinkCollection()->getColumnValues('order_id');
    }

    /**
     * @return Collection
     */
    private function getSubscriptionLinkCollection(): Collection
    {
        $subscriptionLinks = $this->linkCollectionFactory->create();
        $subscriptionLinks->join(
            ['sub' => \Paytrail\PaymentService\Model\ResourceModel\Subscription::PAYTRAIL_SUBSCRIPTIONS_TABLENAME],
            'main_table.subscription_id = sub.entity_id',
        );
        $subscriptionLinks->join(
            'sales_order',
            'main_table.order_id = sales_order.entity_id'
        );
        $select = $subscriptionLinks->getSelect();
        $select->where(
            'sub.status IN (?)',
            \Paytrail\PaymentService\Api\Data\SubscriptionInterface::CLONEABLE_STATUSES
        );
        $select->where(
            'sales_order.status IN (?)',
            $this->orderConfig->getStateDefaultStatus(
                \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT
            )
        );

        $currentDate = new \DateTime();
        $select->where(
            'sub.next_order_date <= ?',
            $currentDate->format('Y-m-d H:i:s')
        );

        return $subscriptionLinks;
    }
}
