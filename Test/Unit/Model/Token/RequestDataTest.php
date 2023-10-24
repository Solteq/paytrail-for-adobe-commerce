<?php

namespace Paytrail\PaymentService\Model\Token;

use Magento\Directory\Api\CountryInformationAcquirerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\ResourceModel\Order\Tax\Item as TaxItem;
use Magento\SalesRule\Model\DeltaPriceRound;
use Magento\Tax\Helper\Data as TaxHelper;
use Paytrail\PaymentService\Gateway\Command\PaymentData;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Logger\PaytrailLogger;
use Paytrail\PaymentService\Model\Company\CompanyRequestData;
use Paytrail\PaymentService\Model\Config\Source\CallbackDelay;
use Paytrail\PaymentService\Model\FinnishReferenceNumber;
use Paytrail\PaymentService\Model\Invoice\Activation\Flag;
use Paytrail\PaymentService\Model\Payment\DiscountSplitter;
use Paytrail\PaymentService\Model\Payment\PaymentDataProvider;
use Paytrail\PaymentService\Model\UrlDataProvider;
use PHPUnit\Framework\TestCase;

class RequestDataTest extends TestCase
{
    private PaymentDataProvider $requestDataObject;

    protected function setUp(): void
    {
        $this->objectManager = new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this);

        $config    = $this->objectManager->getObject(Config::class);
        $taxHelper = $this->objectManager->getObject(TaxHelper::class);

        $priceCurrency = $this->getMockForAbstractClass(PriceCurrencyInterface::class);
        $priceCurrency->method('round')
            ->willReturnCallback(
                function ($amount) {
                    return round($amount, 2);
                }
            );
        $configMock = $this->getMockForAbstractClass(ScopeConfigInterface::class);
        $configMock->method('getValue')->willReturn(24);


        $discountSplitter = new DiscountSplitter(
            new DeltaPriceRound($priceCurrency),
            $configMock
        );


        $taxItems = $this->createMock(TaxItem::class);
        $taxItems->method('getTaxItemsByOrderId')->willReturn([]);
        $this->requestDataObject = new PaymentDataProvider(
            $this->createMock(CompanyRequestData::class),
            $this->createMock(CountryInformationAcquirerInterface::class),
            $taxHelper,
            $discountSplitter,
            $taxItems,
            $this->createMock(UrlDataProvider::class),
            $this->createMock(CallbackDelay::class),
            $this->createMock(FinnishReferenceNumber::class),
            $config,
            $this->createMock(Flag::class),
            $this->createMock(PaytrailLogger::class)
        );
    }


    /**
     * @dataProvider itemArgsDataProvider
     * @return void
     * @magentoConfigFixture tax/calculation/apply_after_discount 1
     * @throws LocalizedException
     */
    public function testItemArgsDiscountTax($input, $discounts, $expected)
    {

        $order = $this->objectManager->getObject(Order::class);
        $order->setData($input['order']);

//        $order->method('getDiscountAmount')->willReturn($discounts);
//        $order->method('getBaseDiscountAmount')->willReturn($discounts);
        $items = $this->prepareOrderItemsMock($input['items']);
        $order->setItems($items);


        $paytrailItems = $this->requestDataObject->getOrderItemLines($order);

        $this->assertEquals(
            number_format($expected['total'] * 100, 2, '.', ''),
            array_reduce($paytrailItems, fn($carry, $item) => $carry + $item->getUnitPrice() * $item->getUnits(), 0),
            'Total does not match'
        );
    }

    /**
     * @return array[]
     */
    public static function itemArgsDataProvider()
    {
        return [
            'base case' => [
                'input'     => [
                    'config'   => [
                        'discount_tax' => 1,
                        'shipping_tax' => 1,
                    ],
                    'shipping' => 12.02,
                    'items'    => [
                        [
                            'qty'             => 3,
                            'price'           => 100,
                            'tax_percent'     => 24,
                            'row_total'       => 300,
                            'name'            => 'test',
                            'discount_amount' => 10,
                        ],


                    ],
                    'order'    =>
                        [
                            'discount_amount'                           => 10,
                            'shipping_amount'                           => 12.02,
                            'shipping_tax_amount'                       => 12.02 * 0.24,
                            'shipping_discount_amount'                  => 0,
                            'shipping_discount_tax_compensation_amount' => 0,
                        ]

                ],
                'discounts' => [
                    'giftcard' => 10,
                ],
                'expected'  => [
                    'total' => number_format(300 - 10 + 12.02 + 12.02 * 0.24, 2, '.', ''),
                    'items' => [
                        [
                            'qty'             => 3,
                            'price'           => 100,
                            'tax_percent'     => 24,
                            'name'            => 'test',
                            'discount_amount' => 10,
                        ],
                        [
                            //shipping item
                            'qty'             => 1,
                            'price'           => 12.02,
                            'tax_percent'     => 24,
                            'name'            => 'Shipping',
                            'discount_amount' => 0,
                        ]
                    ],
                ],
            ]
        ];
    }

    private function prepareOrderItemsMock($items)
    {
        $orderItems = [];
        foreach ($items as $item) {
            $orderItem = $this->createMock(Item::class);
            $orderItem->method('getQtyOrdered')->willReturn($item['qty']);
            $orderItem->method('getPriceInclTax')->willReturn($item['price']);
            $orderItem->method('getTaxPercent')->willReturn($item['tax_percent']);
            $orderItem->method('getBasePriceInclTax')->willReturn($item['price']);
            $orderItem->method('getRowTotalInclTax')->willReturn($item['row_total']);
            $orderItem->method('getDiscountAmount')->willReturn($item['discount_amount']);
            $orderItems[] = $orderItem;
        }
        return $orderItems;
    }
}
