<?php

namespace Paytrail\PaymentService\Model\Ui\DataProvider\Product\Form\Modifier;

use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Framework\Stdlib\ArrayManager;
use Paytrail\PaymentService\Model\Recurring\Config;

class Attributes extends AbstractModifier
{

    /**
     * @param ArrayManager $arrayManager
     * @param Config $recurringConfig
     */
    public function __construct(
        private readonly ArrayManager $arrayManager,
        private readonly Config $recurringConfig
    ) {
    }

    /**
     * ModifyData
     *
     * @param array $data
     *
     * @return array
     */
    public function modifyData(array $data)
    {
        return $data;
    }

    /**
     * ModifyMeta.
     *
     * @param array $meta
     *
     * @return array
     */
    public function modifyMeta(array $meta)
    {
        if (isset($meta['product-details']['children']['container_recurring_payment_schedule'])) {
            $attribute = 'recurring_payment_schedule';
            $path = $this->arrayManager->findPath($attribute, $meta, null, 'children');

            if (!$this->recurringConfig->isRecurringPaymentEnabled()) {
                $meta = $this->arrayManager->set(
                    "{$path}/arguments/data/config/visible",
                    $meta,
                    false
                );
            } else {
                $meta = $this->arrayManager->set(
                    "{$path}/arguments/data/config/visible",
                    $meta,
                    true
                );
            }
        }

        return $meta;
    }
}
