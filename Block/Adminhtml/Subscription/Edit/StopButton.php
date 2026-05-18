<?php

namespace Paytrail\PaymentService\Block\Adminhtml\Subscription\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\App\RequestInterface;
use Paytrail\PaymentService\Api\Data\SubscriptionInterface;
use Paytrail\PaymentService\Api\SubscriptionRepositoryInterface;

class StopButton extends AbstractButton
{
    public function __construct(
        Context $context,
        RequestInterface $request,
        private SubscriptionRepositoryInterface $subscriptionRepository
    ) {
        parent::__construct($context, $request);
    }

    public function getButtonData()
    {
        $data = [];
        $subscriptionStatus = $this->subscriptionRepository->get($this->getId())->getStatus();
        if ($this->getId() && $subscriptionStatus === SubscriptionInterface::STATUS_ACTIVE) {
            $data = [
                'label' => __('Stop schedule'),
                'class' => 'delete',
                'on_click' => 'deleteConfirm(\''
                    . __('Cancel any unpaid orders and prevent new recurring payments from being made?')
                    . '\', \'' . $this->getStopScheduleUrl() . '\')',
                'sort_order' => 40,
            ];
        }

        return $data;
    }

    private function getStopScheduleUrl()
    {
        return $this->getUrl(
            '*/*/stopSchedule',
            [
                'id' => $this->getId()
            ]
        );
    }
}
