<?php
declare(strict_types=1);

namespace Paytrail\PaymentService\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Paytrail\PaymentService\Model\Recurring\Config;

class ScheduledCartValidation implements ObserverInterface
{
    /**
     * ScheduledCartValidation constructor.
     *
     * @param CartRepositoryInterface $cartRepository
     * @param Config $recurringConfig
     */
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly Config $recurringConfig
    ) {
    }

    /**
     * Execute.
     *
     * @param Observer $observer
     *
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        $cartSchedule = null;
        $cartId = $observer->getEvent()->getOrder()->getQuoteId();
        $cart = $this->cartRepository->get($cartId);

        if ($cart->getItems() && $this->recurringConfig->isRecurringPaymentEnabled()) {
            foreach ($cart->getItems() as $cartItem) {
                $cartItemSchedule = $cartItem
                    ->getProduct()
                    ->getCustomAttribute(PreventDifferentScheduledCart::SCHEDULE_CODE);

                if ($cartItemSchedule && $cartItemSchedule->getValue()) {
                    if (null !== $cartSchedule && $cartSchedule !== $cartItemSchedule->getValue()) {
                        throw new LocalizedException(__("Can't place order with different scheduled products in cart"));
                    } else {
                        $cartSchedule = $cartItemSchedule->getValue();
                    }
                }
            }
        }
    }
}
