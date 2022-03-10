<?php
declare(strict_types=1);

namespace Paytrail\PaymentService\Exceptions;

use Paytrail\PaymentService\Logger\PaytrailLogger;

/**
 * Class CheckoutExceptionLogger
 */
class CheckoutExceptionLogger
{
    /**
     * @var PaytrailLogger
     */
    private $paytrailLogger;

    /**
     * @param PaytrailLogger $paytrailLogger
     */
    public function __construct(
        PaytrailLogger $paytrailLogger
    ) {
        $this->paytrailLogger = $paytrailLogger;
    }

    /**
     * @param $errorMessage
     * @throws CheckoutException
     */
    public function processError($errorMessage)
    {
        $this->paytrailLogger->logData(\Monolog\Logger::ERROR, $errorMessage);
        throw new CheckoutException(__($errorMessage));
    }
}
