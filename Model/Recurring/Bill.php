<?php

namespace Paytrail\PaymentService\Model\Recurring;

use Magento\Framework\Exception\LocalizedException;
use Paytrail\PaymentService\Model\Subscription\ActiveOrderProvider;
use Paytrail\PaymentService\Model\Subscription\OrderBiller;

class Bill
{
    /**
     * @param OrderBiller $orderBiller
     * @param ActiveOrderProvider $activeOrders
     */
    public function __construct(
        private readonly OrderBiller $orderBiller,
        private readonly ActiveOrderProvider $activeOrders
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function process()
    {
        $validOrders = $this->getValidOrderIds();

        if (empty($validOrders)) {
            return;
        }
        $this->orderBiller->billOrdersById($validOrders);
    }

    /**
     * @return int[]
     * @throws LocalizedException
     */
    private function getValidOrderIds(): array
    {
        return $this->activeOrders->getPayableOrderIds();
    }
}
