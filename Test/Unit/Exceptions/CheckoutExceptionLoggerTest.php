<?php
declare(strict_types=1);

namespace Paytrail\PaymentService\Test\Unit\Exceptions;

use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Exceptions\CheckoutExceptionLogger;
use PHPUnit\Framework\TestCase;

class CheckoutExceptionLoggerTest extends TestCase
{
    const VALID_ALGORITHM_EXCECTED_VALUE = ["sha256", "sha512"];

    /**
     * @var CheckoutExceptionLogger
     */
    private $checkoutExceptionLogger;

    /**
     * @var \Paytrail\PaymentService\Logger\PaytrailLogger|\PHPUnit\Framework\MockObject\MockObject
     */
    private $paytrailLogger;

    protected function setUp(): void
    {
        $this->paytrailLogger = $this->getMockBuilder(
            \Paytrail\PaymentService\Logger\PaytrailLogger::class
        )->disableOriginalConstructor()
            ->onlyMethods([
                'logData'
            ])->getMock();

        $this->checkoutExceptionLogger = new CheckoutExceptionLogger(
            $this->paytrailLogger
        );
    }

    /**
     * @return void
     * @throws CheckoutException
     */
    public function testProcessError()
    {
        $this->expectException('\Paytrail\PaymentService\Exceptions\CheckoutException');
        $this->checkoutExceptionLogger->processError('error Message');
    }
}
