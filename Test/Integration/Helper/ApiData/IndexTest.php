<?php

namespace Paytrail\PaymentService\Test\Integration\Helper\ApiData;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\TestFramework\Helper\Bootstrap;
use Paytrail\PaymentService\Helper\ApiData;
use Paytrail\SDK\Util\Signature;

class IndexTest extends \Magento\TestFramework\TestCase\AbstractController
{
    const API_STATUS_OK = 'ok';
    const API_STATUS_FAIL = 'fail';
    const API_STATUS_PENDING = 'pending';
    const API_STATUS_DELAYED = 'delayed';
    const SUCCESS_STATUSES = ["processing", "pending_paytrail", "pending", "complete"];
    const CANCEL_STATUSES = ["canceled"];
    const PAYMENT_REQUEST_TYPE = 'payment';
    const REFUND_REQUEST_TYPE = 'refund';
    const EMAIL_REFUND_REQUEST_TYPE = 'email_refund';
    const PAYMENT_PROVIDERS_REQUEST_TYPE = 'payment_providers';

    // NOTE: these are not real test credentials and will not work with real api calls
    const TEST_MERCHANT_ID = '727711';
    const TEST_MERCHANT_SECRET = '4KTewFRLVm.hKnegd3LbsPfrMN-NwvVboavRNylz.L1AVJEVr!CY6GC9PveDD6dMMUfGCccGmtUoN6Gj';

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var TransactionRepositoryInterface
     */
    private $transactionRepository;

    /**
     * @var mixed
     */
    private $apiData;

    /**
     * setUp constructor
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->objectManager = Bootstrap::getObjectManager();
        $this->request = $this->objectManager->get(RequestInterface::class);
        $this->orderRepository = $this->objectManager->get(OrderRepositoryInterface::class);
        $this->transactionRepository = $this->objectManager->get(TransactionRepositoryInterface::class);
        $this->apiData = $this->objectManager->get(ApiData::class);
    }

    /**
     * @dataProvider apiDataDataProvider
     * @magentoDbIsolation disabled
     * @magentoDataFixture Paytrail_PaymentService::Test/Integration/_files/prepare_order.php
     * @magentoConfigFixture default_store payment/paytrail/merchant_id 727711
     * @magentoConfigFixture default_store payment/paytrail/merchant_secret 4KTewFRLVm.hKnegd3LbsPfrMN-NwvVboavRNylz.L1AVJEVr!CY6GC9PveDD6dMMUfGCccGmtUoN6Gj
     * @throws LocalizedException
     */
    public function testApiData($responses, $expected)
    {
        foreach ($responses as $response) {
            $this->request->clearParams();
            $params = $this->getRequestParams($response);
            $this->request->setParams($params);

            foreach ($this->getOrders() as $order) {
                if ($order->getIncrementId() !== '100000001') {
                    $this->fail('Invalid incrementId found');
                }

                $returnData = $this->apiData->processApiRequest(
                    $response['requestType'],
                    $order,
                    1,
                    $params['checkout-transaction-id']
                );
            }

            $this->assertNotEmpty(
                $returnData,
                'Payment methods are empty'
            );
        }
    }

    /**
     * @param $responseData
     * @return array
     * @throws LocalizedException
     */
    private function getRequestParams($responseData)
    {
        $requestParams = [
            'checkout-account' => self::TEST_MERCHANT_ID,
            'checkout-algorithm' => 'sha256',
            'checkout-amount' => 100,
            'checkout-stamp' => hash('sha256', time() . '100000001'),
            'checkout-reference' => '11000 00001 5',
            'checkout-transaction-id' => '5439d22e-9246-11ec-a069-c3b2e5448177',
            'checkout-status' => $responseData['status'],
            'checkout-provider' => $responseData['provider'] ?? 'nordea',
        ];
        $requestParams['signature'] = $this->generateSignature($requestParams);

        return $requestParams;
    }

    /**
     * @param $requestParams
     * @return string
     * @throws LocalizedException
     */
    private function generateSignature($requestParams)
    {
        if (!class_exists(Signature::class)) {
            throw new LocalizedException(__('Paytrail\SDK is not installed unable to complete tests'));
        }

        return Signature::calculateHmac($requestParams, '', self::TEST_MERCHANT_SECRET);
    }

    /**
     * @return \Magento\Sales\Api\Data\OrderSearchResultInterface
     */
    private function getOrders(): \Magento\Sales\Api\Data\OrderSearchResultInterface
    {
        /** @var \Magento\Framework\Api\SearchCriteriaBuilder $criteria */
        $criteria = Bootstrap::getObjectManager()->create(SearchCriteriaBuilder::class);
        $criteria->addFilter(
            'increment_id',
            '100000001'
        );
        $searchCriteria = $criteria->create();

        return $this->orderRepository->getList(
            $searchCriteria
        );
    }

    /**
     * @return array[]
     */
    public function apiDataDataProvider()
    {
        return [
            'RequestType = payment_providers' => [
                'responses' => [
                    [
                        'requestType' => self::PAYMENT_PROVIDERS_REQUEST_TYPE,
                        'status' => self::API_STATUS_OK,
                    ],
                ],
                'expected' => ''
            ],
            'RequestType = email_refund' => [
                'responses' => [
                    [
                        'requestType' => self::EMAIL_REFUND_REQUEST_TYPE,
                        'status' => self::API_STATUS_OK,
                    ],
                ],
                'expected' => ''
            ],
        ];
    }
}
