<?php

namespace Paytrail\PaymentService\Test\Integration\Controller\Receipt;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\TestFramework\Helper\Bootstrap;
use Paytrail\SDK\Util\Signature;

class IndexTest extends \Magento\TestFramework\TestCase\AbstractController
{
    const API_STATUS_OK = 'ok';
    const API_STATUS_FAIL = 'fail';
    const API_STATUS_PENDING = 'pending';
    const API_STATUS_DELAYED = 'delayed';
    const SUCCESS_STATUSES = ["processing", "pending_paytrail", "pending", "complete"];
    const CANCEL_STATUSES = ["canceled"];

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
    }

    /**
     * @dataProvider receiptDataProvider
     * @magentoDbIsolation disabled
     * @magentoDataFixture Paytrail_PaymentService::Test/Integration/_files/prepare_order.php
     * @magentoConfigFixture default_store payment/paytrail/merchant_id 727711
     * @magentoConfigFixture default_store payment/paytrail/merchant_secret 4KTewFRLVm.hKnegd3LbsPfrMN-NwvVboavRNylz.L1AVJEVr!CY6GC9PveDD6dMMUfGCccGmtUoN6Gj
     * @throws LocalizedException
     */
    public function testReceipt($responses, $expected)
    {
        $okIndex = 999;
        foreach ($responses as $index => $response) {
            $this->request->clearParams();
            $params = $this->getRequestParams($response);
            $this->request->setParams($params);
            $this->dispatch('paytrail/receipt/index');

            foreach ($this->getOrders() as $order) {
                if ($order->getIncrementId() !== '100000001') {
                    $this->fail('Invalid incrementId found');
                }
            }

            if ($response['status'] == self::API_STATUS_OK && $index < $okIndex) {
                $okIndex = $index;
                $paymentDetails = $expected['paymentDetails'];
                $paymentDetails['stamp'] = $params['checkout-stamp'];
            }

            $this->assertEquals(
                $expected['order_status'],
                $order->getStatus(),
                'Order status incorrect'
            );

            if (in_array($order->getStatus(), self::CANCEL_STATUSES)) {
                $this->dispatch('paytrail/callback/index');
                if (!empty($paymentDetails)) {
                    $this->validateSuccessfulPayment($order, $params['checkout-transaction-id'], $paymentDetails);
                    $this->assertRedirect();
                }
                else {
                    $this->validateFailedPayment($order, $params['checkout-transaction-id']);
                    $this->assertRedirect();
                }
            }
        }

        if (!empty($paymentDetails)) {
            $this->validateSuccessfulPayment($order, $params['checkout-transaction-id'], $paymentDetails);
        }
        else {
            $this->validateFailedPayment($order, $params['checkout-transaction-id']);
        }
    }

    /**
     * @param Order $order
     * @param $checkouttransactionid
     * @param array $paymentDetails
     * @return void
     * @throws \Magento\Framework\Exception\InputException
     */
    private function validateSuccessfulPayment(Order $order, $checkouttransactionid, array $paymentDetails): void
    {
        $payment = $order->getPayment();
        $this->assertEquals(
            $checkouttransactionid,
            $payment->getLastTransId(),
            "Payment LastTransId doesn't match"
        );

        $transaction = $this->transactionRepository->getByTransactionId(
            $checkouttransactionid,
            $payment->getEntityId(),
            $order->getId()
        );

        $this->assertNotFalse($transaction, 'Transaction was unable to be loaded');

        $this->assertEquals(
            $paymentDetails['orderNo'],
            $transaction->getAdditionalInformation()['raw_details_info']['orderNo']
        );

        $this->assertEquals(
            $paymentDetails['stamp'],
            $transaction->getAdditionalInformation()['raw_details_info']['stamp']
        );

        $orderInvoice = $order->getInvoiceCollection()->getLastItem();
        $this->assertNotEmpty(
            $orderInvoice,
            'Invoice is missing'
        );

        $this->assertEquals(
            $order->getGrandTotal(),
            $orderInvoice->getGrandTotal(),
            "Order total price doesn't match"
        );

        $this->assertEquals(
            $checkouttransactionid,
            $orderInvoice->getTransactionId(),
            "Transaction id doesn't match"
        );

        $this->assertEquals(
            Invoice::STATE_PAID,
            $orderInvoice->getState(),
            "Invoice status doesn't match"
        );
    }

    /**
     * @param Order $order
     * @param $checkouttransactionid
     * @param array $paymentDetails
     * @return void
     * @throws \Magento\Framework\Exception\InputException
     */
    private function validateFailedPayment(Order $order, $checkouttransactionid): void
    {
        $payment = $order->getPayment();

        $this->assertEmpty(
            $payment->getLastTransId(),
            'Payment should not exist'
        );

        $this->assertEmpty(
            $this->transactionRepository->getByTransactionId(
                $checkouttransactionid,
                $payment->getEntityId(),
                $order->getId()
            ),
            'Transaction should not exist'
        );

        $this->assertEmpty(
            $order->getInvoiceCollection(),
            'Invoice should not exist'
        );

        $this->assertRedirect();
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
    public function ReceiptDataProvider()
    {
        return [
            'Status pending_payment' => [
                'responses' => [
                    [
                        'status' => self::API_STATUS_OK,
                    ]
                ],
                'expected' => [
                    'paymentDetails' => [
                        'orderNo' => '100000001',
                        'method' => ''
                    ],
                    'order_status' => 'processing'
                ],
            ],
            'Single Ok response' => [
                'responses' => [
                    [
                        'status' => self::API_STATUS_OK,
                    ]
                ],
                'expected' => [
                    'paymentDetails' => [
                        'orderNo' => '100000001',
                        'method' => ''
                    ],
                    'order_status' => 'processing'
                ],
            ],
            'Two ok responses' => [
                'responses' => [
                    [
                        'status' => self::API_STATUS_OK,
                    ],
                    [
                        'status' => self::API_STATUS_OK,
                    ],
                ],
                'expected' => [
                    'paymentDetails' => [
                        'orderNo' => '100000001',
                        'method' => ''
                    ],
                    'order_status' => 'processing'
                ]
            ],
            'One fail one ok response' => [
                'responses' => [
                    [
                        'status' => self::API_STATUS_FAIL,
                    ],
                    [
                        'status' => self::API_STATUS_OK,
                    ],
                ],
                'expected' => [
                    'paymentDetails' => [
                        'orderNo' => '100000001',
                        'method' => ''
                    ],
                    'order_status' => 'processing'
                ]
            ],
            'Delayed ok response' => [
                'responses' => [
                    [
                        'status' => self::API_STATUS_DELAYED,
                    ],
                    [
                        'status' => self::API_STATUS_OK,
                    ],
                ],
                'expected' => [
                    'paymentDetails' => [
                        'orderNo' => '100000001',
                        'method' => ''
                    ],
                    'order_status' => 'processing'
                ]
            ],
            'One ok one fail response' => [
                'responses' => [
                    [
                        'status' => self::API_STATUS_OK,
                    ],
                    [
                        'status' => self::API_STATUS_FAIL,
                    ],
                ],
                'expected' => [
                    'paymentDetails' => [
                        'orderNo' => '100000001',
                        'method' => ''
                    ],
                    'order_status' => 'processing'
                ]
            ],
            'One pending one ok response' => [
                'responses' => [
                    [
                        'status' => self::API_STATUS_PENDING,
                    ],
                    [
                        'status' => self::API_STATUS_OK,
                    ],
                ],
                'expected' => [
                    'paymentDetails' => [
                        'orderNo' => '100000001',
                        'method' => ''
                    ],
                    'order_status' => 'processing'
                ]
            ],
            'Two fail response' => [
                'responses' => [
                    [
                        'status' => self::API_STATUS_FAIL,
                    ],
                    [
                        'status' => self::API_STATUS_FAIL,
                    ],
                ],
                'expected' => [
                    'paymentDetails' => [
                        'orderNo' => '100000001',
                        'method' => ''
                    ],
                    'order_status' => 'canceled'
                ]
            ]
        ];
    }
}
