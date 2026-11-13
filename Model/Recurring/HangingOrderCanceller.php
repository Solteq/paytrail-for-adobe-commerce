<?php

namespace Paytrail\PaymentService\Model\Recurring;

use Exception;
use Magento\Sales\Api\OrderManagementInterface;
use Paytrail\PaymentService\Model\ResourceModel\Subscription;
use Psr\Log\LoggerInterface;

class HangingOrderCanceller
{
    /**
     * @param Subscription $subscriptionResource
     * @param OrderManagementInterface $orderManagement
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Subscription $subscriptionResource,
        private readonly OrderManagementInterface $orderManagement,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Cancels cloned orders that are still pending while their subscription has already been closed.
     *
     * @return void
     */
    public function process(): void
    {
        try {
            $orderIds = $this->subscriptionResource->getHangingClosedSubscriptionOrderIds();
        } catch (Exception $exception) {
            $this->logger->error(__(
                'Failed to fetch hanging subscription orders for cancellation: %error',
                ['error' => $exception->getMessage()]
            ));
            return;
        }

        foreach ($orderIds as $orderId) {
            try {
                $this->orderManagement->cancel($orderId);
            } catch (Exception $exception) {
                $this->logger->error(__(
                    'Failed to cancel hanging subscription order %id: %error',
                    ['id' => $orderId, 'error' => $exception->getMessage()]
                ));
            }
        }
    }
}
