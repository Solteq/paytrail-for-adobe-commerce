<?php

namespace Paytrail\PaymentService\Controller\Adminhtml\Recurring;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use Paytrail\PaymentService\Model\ResourceModel\Subscription;
use Paytrail\PaymentService\Model\ResourceModel\Subscription\CollectionFactory;

class MassDelete implements HttpPostActionInterface
{
    public function __construct(
        private Context           $context,
        private Filter            $filter,
        private CollectionFactory $factory,
        private Subscription      $subscription
    ) {
    }

    public function execute()
    {
        $resultRedirect = $this->context->getResultFactory()->create(ResultFactory::TYPE_REDIRECT);

        $collection     = $this->filter->getCollection($this->factory->create());
        $collectionSize = $collection->getSize();

        foreach ($collection as $item) {
            $this->subscription->delete($item);
        }

        $this->context->getMessageManager()->addSuccessMessage(
            __('A total of %1 record(s) have been deleted.', $collectionSize)
        );

        return $resultRedirect->setPath('*/*/');
    }
}
