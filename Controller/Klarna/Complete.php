<?php

namespace Paytrail\PaymentService\Controller\Klarna;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Paytrail\PaymentService\Logger\PaytrailLogger;
use Paytrail\PaymentService\Model\Klarna\ExpressCheckoutService;

/**
 * AJAX endpoint handling Klarna Web SDK's "complete" event: places the Magento order and charges it
 * through Paytrail.
 */
class Complete implements ActionInterface
{
    /**
     * Complete constructor.
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
            $payload = [
                'shippingAddress' => (array) $this->request->getParam('shippingAddress', []),
                'shippingOptionReference' => (string) $this->request->getParam('shippingOptionReference', ''),
                'networkSessionToken' => (string) $this->request->getParam('networkSessionToken', ''),
            ];

            if ($payload['networkSessionToken'] === '') {
                throw new \InvalidArgumentException('Missing networkSessionToken');
            }

            return $resultJson->setData($this->expressCheckoutService->completePurchase($payload));
        } catch (\Exception $e) {
            $this->log->error(\sprintf('Klarna Express Checkout: complete purchase failed: %s', $e->getMessage()));

            return $resultJson->setData([
                'success' => false,
                'message' => __('Unable to complete the purchase.')->render(),
            ]);
        }
    }
}
