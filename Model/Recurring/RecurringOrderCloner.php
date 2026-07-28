<?php

namespace Paytrail\PaymentService\Model\Recurring;

use Magento\Framework\Exception\LocalizedException;
use Paytrail\PaymentService\Model\Subscription\Email;
use Paytrail\PaymentService\Model\Subscription\OrderCloner;
use Paytrail\PaymentService\Model\Subscription\SubscriptionLinkRepository;
use Paytrail\PaymentService\Model\ResourceModel\Subscription;
use Psr\Log\LoggerInterface;

class RecurringOrderCloner
{
    /**
     * @param OrderCloner $orderCloner
     * @param Subscription $subscriptionResource
     * @param Email $email
     * @param SubscriptionLinkRepository $subscriptionLinkRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly OrderCloner $orderCloner,
        private readonly Subscription $subscriptionResource,
        private readonly Email $email,
        private readonly SubscriptionLinkRepository $subscriptionLinkRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Clones recurring payments that are due in the next payment period and notifies customer's whos orders were
     * cloned.
     *
     * @return void
     */
    public function process()
    {
        $validIds = $this->getValidOrderIds();
        if (empty($validIds)) {
            return;
        }

        $clonedOrders = $this->orderCloner->cloneOrders($validIds);

        foreach ($clonedOrders as $parentId => $clonedOrder) {
            $this->subscriptionLinkRepository->linkOrderToSubscription(
                $clonedOrder->getId(),
                $this->subscriptionLinkRepository->getSubscriptionIdFromOrderId($parentId)
            );
        }

        $this->email->sendNotifications($clonedOrders);
    }

    private function getValidOrderIds()
    {
        try {
            return $this->subscriptionResource->getClonableOrderIds();
        } catch (LocalizedException $e) {
            $this->logger->error(\__(
                'Recurring Payment unable to fetch clonable order ids: %error',
                ['error' => $e->getMessage()]
            ));

            return [];
        }
    }
}
