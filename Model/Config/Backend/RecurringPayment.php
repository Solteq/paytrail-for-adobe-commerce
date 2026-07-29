<?php

namespace Paytrail\PaymentService\Model\Config\Backend;

use Magento\Framework\App\Config\Value;

class RecurringPayment extends Value
{
    public function beforeSave()
    {
        $skipBankSelection = $this->_config->getValue(
            'payment/paytrail/skip_bank_selection',
            $this->getScope(),
            $this->getScopeCode()
        );

        if ($skipBankSelection) {
            throw new LocalizedException(
                __('Recurring payments cannot be enabled when "Payment method selection on a separate page" is enabled in Paytrail payment method settings. 
                Please disable "Payment method selection on a separate page" first.')
            );
        }

        return parent::beforeSave();
    }
}
