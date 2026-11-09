<?php

namespace Paytrail\PaymentService\Controller\Klarna;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Paytrail\PaymentService\Logger\PaytrailLogger;
use Paytrail\PaymentService\Model\Klarna\ExpressCheckoutService;

/**
 * AJAX endpoint handling Klarna Web SDK's "shippingaddresschange" event.
 */
class EstimateShipping implements ActionInterface
{
    /**
     * EstimateShipping constructor.
     *
     * @param RequestInterface $request
     * @param ResultFactory $resultFactory
     * @param ExpressCheckoutService $expressCheckoutService
     * @param PaytrailLogger $log
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResultFactory $resultFactory,
        private readonly ExpressCheckoutService $expressCheckoutService,
        private readonly PaytrailLogger $log
    ) {
    }

    /**
     * Execute function
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            $address = (array) $this->request->getParam('shippingAddress', []);

            return $resultJson->setData([
                'success' => true,
            ] + $this->expressCheckoutService->estimateShipping($address));
        } catch (\Exception $e) {
            $this->log->error(\sprintf('Klarna Express Checkout: estimate shipping failed: %s', $e->getMessage()));

            return $resultJson->setData([
                'success' => false,
                'message' => __('Unable to calculate shipping options for the given address.')->render(),
            ]);
        }
    }
}
