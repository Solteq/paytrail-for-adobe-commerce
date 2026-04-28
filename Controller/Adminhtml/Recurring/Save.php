<?php

namespace Paytrail\PaymentService\Controller\Adminhtml\Recurring;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Paytrail\PaymentService\Api\Data\SubscriptionLinkInterfaceFactory;
use Paytrail\PaymentService\Api\SubscriptionRepositoryInterface;

class Save implements HttpPostActionInterface
{
    /**
     * Save constructor.
     *
     * @param Context $context
     * @param SubscriptionRepositoryInterface $paymentRepo
     * @param SubscriptionLinkInterfaceFactory $factory
     */
    public function __construct(
        private Context                          $context,
        private SubscriptionRepositoryInterface  $paymentRepo,
        private SubscriptionLinkInterfaceFactory $factory
    ) {
    }

    /**
     * Execute the save action for a recurring payment subscription.
     *
     * @return ResponseInterface|Redirect|(Redirect&ResultInterface)|ResultInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute()
    {
        try {
            $id = $this->context->getRequest()->getParam('entity_id');

            if ($id) {
                $payment = $this->paymentRepo->get($id);
            } else {
                $payment = $this->factory->create();
            }

            $data = $this->context->getRequest()->getParams();
            $payment->setData($data);
            $resultRedirect = $this->context->getResultFactory()->create(ResultFactory::TYPE_REDIRECT);

            $this->paymentRepo->save($payment);
            $resultRedirect->setPath('recurring_payments/recurring');
        } catch (CouldNotSaveException $e) {
            $this->context->getMessageManager()->addErrorMessage($e->getMessage());
            $resultRedirect->setPath('recurring_payments/recurring/edit', ['id' => $id]);
        }

        return $resultRedirect;
    }
}
