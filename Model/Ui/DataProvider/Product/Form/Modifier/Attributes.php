<?php

namespace Paytrail\PaymentService\Model\Ui\DataProvider\Product\Form\Modifier;

use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Framework\Stdlib\ArrayManager;
use Paytrail\PaymentService\Model\Recurring\TotalConfigProvider;

class Attributes extends AbstractModifier
{
    /**
     * @var Magento\Framework\Stdlib\ArrayManager
     */
    private $arrayManager;

    /**
     * @var TotalConfigProvider
     */
    private $totalConfigProvider;

    /**
     * @param ArrayManager $arrayManager
     * @param TotalConfigProvider $totalConfigProvider
     */
    public function __construct(
        ArrayManager $arrayManager,
        TotalConfigProvider $totalConfigProvider
    ) {
        $this->arrayManager = $arrayManager;
        $this->totalConfigProvider = $totalConfigProvider;
    }

    /**
     * ModifyData
     *
     * @param array $data
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
     * @return array
     */
    public function modifyMeta(array $meta)
    {
        if (!$this->totalConfigProvider->isRecurringPaymentEnabled()) {
            $attribute = 'recurring_payment_schedule';
            $path = $this->arrayManager->findPath($attribute, $meta, null, 'children');
            $meta = $this->arrayManager->set(
                "{$path}/arguments/data/config/visible",
                $meta,
                false
            );
        }

        return $meta;
    }
}
