<?php
declare(strict_types=1);

namespace Paytrail\PaymentService\Test\Unit\Model;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\HTTP\Header;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Module\Manager;
use Magento\Sales\Model\Order;
use Paytrail\PaymentService\Gateway\Config\Config as GatewayConfig;
use Paytrail\PaymentService\Model\OrderReference;
use PHPUnit\Framework\TestCase;

class OrderReferenceTest extends TestCase
{
    const REFERENCE_TEST_VALUE = '11000 00001 5';
    const REFERENCE_EXPECTED_VALUE = '100000001';
    const ORDER_REFERENCE_NUMBER_EXPECTED_VALUE = '11000 00001 5';

    /**
     * @var OrderReference
     */
    private $orderReference;

    protected function setUp(): void
    {
        $encoderMock = $this->getMockForAbstractClass(\Magento\Framework\Url\EncoderInterface::class);
        $decoderMock = $this->getMockForAbstractClass(\Magento\Framework\Url\DecoderInterface::class);
        $loggerMock = $this->getMockForAbstractClass(\Psr\Log\LoggerInterface::class);
        $managerMock = new Manager(
            $this->getMockForAbstractClass(\Magento\Framework\Module\Output\ConfigInterface::class),
            $this->getMockForAbstractClass(\Magento\Framework\Module\ModuleListInterface::class)
        );
        $requestMock = $this->getMockForAbstractClass(\Magento\Framework\App\RequestInterface::class);
        $configInterfaceMock = $this->getMockForAbstractClass(\Magento\Framework\Cache\ConfigInterface::class);
        $managerInterfaceMock = $this->getMockForAbstractClass(\Magento\Framework\Event\ManagerInterface::class);
        $urlMock = $this->getMockForAbstractClass(\Magento\Framework\UrlInterface::class);
        $headerMock = new Header(
            $this->getMockForAbstractClass(\Magento\Framework\App\RequestInterface::class),
            $this->getMockForAbstractClass(\Magento\Framework\Stdlib\StringUtils::class)
        );
        $remoteMock = new RemoteAddress(
            $this->getMockForAbstractClass(\Magento\Framework\App\RequestInterface::class)
        );
        $scopeConfigMock = $this->getMockForAbstractClass(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $configMock = new GatewayConfig(
            $this->getMockForAbstractClass(\Magento\Framework\App\Config\ScopeConfigInterface::class),
            $this->getMockForAbstractClass(\Magento\Framework\Encryption\EncryptorInterface::class)
        );

        $contextMock = new Context(
            $encoderMock,
            $decoderMock,
            $loggerMock,
            $managerMock,
            $requestMock,
            $configInterfaceMock,
            $managerInterfaceMock,
            $urlMock,
            $headerMock,
            $remoteMock,
            $scopeConfigMock
        );

        /** @var \Paytrail\PaymentService\Model\OrderReference $orderReference */
        $this->orderReference = new OrderReference(
            $contextMock,
            $configMock
        );
    }

    /**
     * @dataProvider calculateOrderReferenceNumberDataProvider
     */
    public function testCalculateOrderReferenceNumber($data, $expected)
    {
        $this->assertEquals(
            $this->orderReference->calculateOrderReferenceNumber($data[0]['incrementId']),
            $expected['orderReferenceNumber']
        );
    }

    /**
     * @dataProvider getIdFromOrderReferenceNumberDataProvider
     */
    public function testGetIdFromOrderReferenceNumber($data, $expected)
    {
        $this->assertEquals(
            $this->orderReference->getIdFromOrderReferenceNumber($data[0]['reference']),
            $expected['referenceNumber']
        );
    }

    /**
     * @dataProvider getReferenceDataProvider
     */
    public function testGetReference($data, $expected)
    {
        $this->assertEquals(
            $this->orderReference->getReference($this->createOrderMock()),
            $expected['reference']
        );
    }

    /**
     * @param $items
     * @param $discounts
     * @return Order|\PHPUnit\Framework\MockObject\MockObject
     */
    private function createOrderMock()
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->setIncrementId('100000001')
            ->setState(Order::STATE_PENDING_PAYMENT)
            ->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_PENDING_PAYMENT))
            ->setSubtotal(100)
            ->setGrandTotal(100)
            ->setBaseSubtotal(100)
            ->setBaseGrandTotal(100)
            ->setOrderCurrencyCode('EUR')
            ->setBaseCurrencyCode('EUR')
            ->setCustomerIsGuest(true)
            ->setCustomerEmail('customer@null.com');

        return $order;
    }

    public function calculateOrderReferenceNumberDataProvider()
    {
        return [
            'incrementId valid' => [
                'data' => [
                    [
                        'incrementId' => '100000001',
                    ],
                ],
                'expected' => [
                    'orderReferenceNumber' => self::ORDER_REFERENCE_NUMBER_EXPECTED_VALUE,
                ]
            ],
        ];
    }

    public function getIdFromOrderReferenceNumberDataProvider()
    {
        return [
            'Reference valid' => [
                'data' => [
                    [
                        'reference' => self::REFERENCE_TEST_VALUE,
                    ],
                ],
                'expected' => [
                    'referenceNumber' => self::REFERENCE_EXPECTED_VALUE,
                ]
            ],
        ];
    }

    public function getReferenceDataProvider()
    {
        return [
            'incrementId valid' => [
                'data' => [
                    [
                        'incrementId' => '100000001',
                    ],
                ],
                'expected' => [
                    'reference' => self::REFERENCE_EXPECTED_VALUE,
                ]
            ],
        ];
    }
}
