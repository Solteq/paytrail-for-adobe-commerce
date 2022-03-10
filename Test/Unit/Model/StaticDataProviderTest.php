<?php
declare(strict_types=1);

namespace Paytrail\PaymentService\Test\Unit\Model;

use Magento\Framework\Locale\Resolver;
use Paytrail\PaymentService\Model\StaticDataProvider;
use PHPUnit\Framework\TestCase;

class StaticDataProviderTest extends TestCase
{
    const VALID_ALGORITHM_EXCECTED_VALUE = ["sha256", "sha512"];

    /**
     * @var StaticDataProvider
     */
    private $staticDataProvider;

    /**
     * @var Resolver|\PHPUnit\Framework\MockObject\MockObject
     */
    private $localeResolverMock;

    protected function setUp(): void
    {
        $this->localeResolverMock = $this->getMockBuilder(
            \Magento\Framework\Locale\Resolver::class
        )->disableOriginalConstructor()
            ->onlyMethods([
                'getLocale'
            ])->getMock();

        $this->staticDataProvider = new StaticDataProvider(
            $this->localeResolverMock
        );
    }

    /**
     * @dataProvider getValidAlgorithmsDataProvider
     */
    public function testGetValidAlgorithms($expected)
    {
        $this->assertEquals(
            $this->staticDataProvider->getValidAlgorithms(),
            $expected['data']
        );
    }

    /**
     *@dataProvider getStoreLocaleForPaymentProviderDataProvider
     */
    public function testGetStoreLocaleForPaymentProvider($data, $expected)
    {
        $this->localeResolverMock
            ->expects($this->atLeastOnce())
            ->method('getLocale')
            ->willReturn($data['getLocale']);
        $this->assertEquals(
            $this->staticDataProvider->getStoreLocaleForPaymentProvider(),
            $expected['locale']
        );
    }

    public function getValidAlgorithmsDataProvider()
    {
        return [
            'Valid algorithm' => [
                'expected' => [
                    'data' => self::VALID_ALGORITHM_EXCECTED_VALUE,
                ]
            ],
        ];
    }

    public function getStoreLocaleForPaymentProviderDataProvider()
    {
        return [
            'Locale = fi_FI' => [
                'data' => [
                    'getLocale' => 'fi_FI'
                ],
                'expected' => [
                    'locale' => 'FI',
                ]
            ],
            'Locale = sv_SE' => [
                'data' => [
                    'getLocale' => 'sv_SE'
                ],
                'expected' => [
                    'locale' => 'SV',
                ]
            ],
            'Locale empty' => [
                'data' => [
                    'getLocale' => ''
                ],
                'expected' => [
                    'locale' => 'EN',
                ]
            ],
        ];
    }
}
