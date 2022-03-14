<?php
declare(strict_types=1);

namespace Paytrail\PaymentService\Test\Unit\Logger;

use Paytrail\PaymentService\Logger\CheckoutDataLogger;
use PHPUnit\Framework\TestCase;

class CheckoutDataLoggerTest extends TestCase
{
    private const LOGGER_INFO = '200';

    /**
     * @var CheckoutDataLogger
     */
    private $checkoutDataLogger;

    /**
     * @var \Paytrail\PaymentService\Logger\PaytrailLogger|\PHPUnit\Framework\MockObject\MockObject
     */
    private $paytrailLoggerMock;

    /**
     * @var \Paytrail\PaymentService\Gateway\Config\Config|\PHPUnit\Framework\MockObject\MockObject
     */
    private $gatewayConfigMock;

    protected function setUp(): void
    {
        $this->paytrailLoggerMock = $this->getMockBuilder(
            \Paytrail\PaymentService\Logger\PaytrailLogger::class
        )->disableOriginalConstructor()
            ->onlyMethods([
                'resolveLogLevel'
            ])->getMock();

        $this->gatewayConfigMock = $this->getMockBuilder(
            \Paytrail\PaymentService\Gateway\Config\Config::class
        )->disableOriginalConstructor()
            ->onlyMethods([
                'getRequestLog'
            ])->getMock();

        $this->checkoutDataLogger = new CheckoutDataLogger(
            $this->paytrailLoggerMock,
            $this->gatewayConfigMock
        );
    }

    /**
     * @dataProvider logCheckoutDataDataProvider
     */
    public function testLogCheckoutData($data, $expected)
    {
        $this->gatewayConfigMock
            ->expects($this->any())
            ->method('getRequestLog')
            ->willReturn($expected['getRequestLog']);

        $this->paytrailLoggerMock
            ->expects($this->any())
            ->method('resolveLogLevel')
            ->willReturn($expected['resolveLogLevel']);

        $this->checkoutDataLogger->logCheckoutData($data['logType'], $data['level'], $data['data']);
    }

    public function logCheckoutDataDataProvider()
    {
        return [
            'return null test, logType = request' => [
                'data' => [
                    'logType' => 'request',
                    'level' => 'notError',
                    'data' => ''
                ],
                'expected' => [
                    'return' => null,
                    'getRequestLog' => false,
                    'resolveLogLevel' => '',
                    'logData' => false
                ]
            ],
            'return null test, logType = response' => [
                'data' => [
                    'logType' => 'response',
                    'level' => 'notError',
                    'data' => ''
                ],
                'expected' => [
                    'return' => null,
                    'getRequestLog' => false,
                    'resolveLogLevel' => '',
                    'logData' => false
                ]
            ]
        ];
    }
}
