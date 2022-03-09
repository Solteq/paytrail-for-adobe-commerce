<?php
declare(strict_types=1);

namespace Paytrail\PaymentService\Model;

use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order;
use Paytrail\PaymentService\Gateway\Config\Config;

/**
 * OrderReference class
 */
class OrderReference extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var Config
     */
    private $gatewayConfig;

    /**
     * @param Config $gatewayConfig
     */
    public function __construct(
        Context $context,
        Config $gatewayConfig
    ) {
        $this->gatewayConfig = $gatewayConfig;
        parent::__construct($context);
    }

    /**
     * Calculate Finnish reference number from order increment id
     * @param string $incrementId
     * @return string
     */
    public function calculateOrderReferenceNumber($incrementId)
    {
        $prefixedId = '1' . $incrementId;

        $sum = 0;
        $length = strlen($prefixedId);

        for ($i = 0; $i < $length; ++$i) {
            $sum += substr($prefixedId, -1 - $i, 1) * [7, 3, 1][$i % 3];
        }
        $num = (10 - $sum % 10) % 10;
        $referenceNum = $prefixedId . $num;

        return trim(chunk_split($referenceNum, 5, ' '));
    }

    /**
     * Get order increment id from checkout reference number
     * @param string $reference
     * @return string|null
     */
    public function getIdFromOrderReferenceNumber($reference)
    {
        return preg_replace('/\s+/', '', substr($reference, 1, -1));
    }

    /**
     * @param Order $order
     * @return string reference number
     */
    public function getReference($order)
    {
        return $this->gatewayConfig->getGenerateReferenceForOrder()
            ? $this->calculateOrderReferenceNumber($order->getIncrementId())
            : $order->getIncrementId();
    }
}
