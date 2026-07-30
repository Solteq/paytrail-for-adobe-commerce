<?php

namespace Paytrail\PaymentService\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

class RecurringPayment extends Value
{
    /**
     * Not allowed to enable recurring payments when "Payment method selection on a separate page" is enabled.
     *
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $skipBankSelection = $this->_config->getValue(
            'payment/paytrail/skip_bank_selection',
            $this->getScope(),
            $this->getScopeCode()
        );

        if ($skipBankSelection && $this->getValue()) {
            throw new LocalizedException(
                __('Recurring payments cannot be enabled when "Payment method selection on a separate page"'
                    .' is enabled in Paytrail payment method settings.'
                    . PHP_EOL
                    . 'Please disable "Payment method selection on a separate page" first.'
                )
            );
        }

        return parent::beforeSave();
    }
}
