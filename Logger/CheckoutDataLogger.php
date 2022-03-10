<?php
declare(strict_types=1);

namespace Paytrail\PaymentService\Logger;

use Paytrail\PaymentService\Gateway\Config\Config;

/**
 * Class CheckoutDataLogger
 */
class CheckoutDataLogger
{
    /**
     * @var Config
     */
    private $gatewayConfig;

    /** @var PaytrailLogger */
    private $paytrailLogger;

    /**
     * @param PaytrailLogger $paytrailLogger
     * @param Config $gatewayConfig
     */
    public function __construct(
        PaytrailLogger $paytrailLogger,
        Config         $gatewayConfig
    ) {
        $this->paytrailLogger = $paytrailLogger;
        $this->gatewayConfig = $gatewayConfig;
    }

    /**
     * @param string $logType
     * @param string $level
     * @param mixed $data
     *
     * @deprecated implementation replaced by dedicated logger class
     * @see \Paytrail\PaymentService\Logger\PaytrailLogger::logData
     */
    public function logCheckoutData($logType, $level, $data)
    {
        if (
            $level !== 'error' &&
            (($logType === 'request' && $this->gatewayConfig->getRequestLog() == false)
                || ($logType === 'response' && $this->gatewayConfig->getResponseLog() == false))
        ) {
            return;
        }

        $level = $level == 'error' ? $level : $this->paytrailLogger->resolveLogLevel($logType);
        $this->paytrailLogger->logData($level, $data);
    }
}
