<?php

namespace Paytrail\PaymentService\Model\Subscription;

use DateMalformedStringException;
use DateTime;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address\Renderer;
use Magento\Store\Model\App\Emulation;
use Paytrail\PaymentService\Api\SubscriptionLinkRepositoryInterface;
use Paytrail\PaymentService\Api\SubscriptionRepositoryInterface;
use Paytrail\PaymentService\Model\Recurring\Config;
use Psr\Log\LoggerInterface;

class Email
{
    public const XML_PATH_EMAIL_TEMPLATE                 = 'sales/recurring_payment/email_template';
    public const XML_PATH_EMAIL_ORDER_CREATION_LEAD_DAYS = 'sales/recurring_payment/warning_period';

    /**
     * @param TransportBuilder $transportBuilder
     * @param Emulation $emulation
     * @param Renderer $addressRenderer
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     * @param SubscriptionRepositoryInterface $subscriptionRepository
     * @param SubscriptionLinkRepositoryInterface $subscriptionLinkRepository
     * @param Config $recurringConfig
     */
    public function __construct(
        private readonly TransportBuilder $transportBuilder,
        private readonly Emulation $emulation,
        private readonly Renderer $addressRenderer,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger,
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly SubscriptionLinkRepositoryInterface $subscriptionLinkRepository,
        private readonly Config $recurringConfig,
    ) {
    }

    /**
     * Send email notifications for the given orders.
     *
     * @param Order[] $clonedOrders
     */
    public function sendNotifications(array $clonedOrders)
    {
        foreach ($clonedOrders as $order) {
            $this->notify($order);
        }
    }

    /**
     * Notify the customer about the order.
     *
     * @param Order $order
     */
    private function notify($order)
    {
        try {
            $transport = $this->transportBuilder->setTemplateIdentifier($this->getEmailTemplateId($order))
                ->setTemplateOptions($this->getTemplateOptions($order))
                ->setTemplateVars($this->prepareTemplateVars($order))
                ->setFromByScope(
                    'sales',
                    $order->getStoreId()
                )->addTo($order->getCustomerEmail())
                ->getTransport();

            $this->emulation->startEnvironmentEmulation($order->getStoreId());
            $transport->sendMessage();
            $this->emulation->stopEnvironmentEmulation();
        } catch (\Exception $e) {
            $this->logger->info($e->getMessage());
        }
    }

    /**
     * Get email template id.
     *
     * @param Order $order
     *
     * @return string
     */
    private function getEmailTemplateId($order)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_EMAIL_TEMPLATE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $order->getStoreId()
        );
    }

    /**
     * Prepare template variables.
     *
     * @param Order $order
     *
     * @return string[]
     * @throws NoSuchEntityException
     */
    private function prepareTemplateVars($order): array
    {
        return [
            'order'                    => $order,
            'order_id'                 => $order->getId(),
            'billing'                  => $order->getBillingAddress(),
            'payment_html'             => $this->getPaymentHtml($order),
            'store'                    => $order->getStore(),
            'formattedShippingAddress' => $this->getFormattedShippingAddress($order),
            'formattedBillingAddress'  => $this->getFormattedBillingAddress($order),
            'created_at_formatted'     => $order->getCreatedAtFormatted(2),
            'warning_period'           => $this->getTimeToNextOrder($order),
            'order_data'               => [
                'customer_name'         => $order->getCustomerName(),
                'is_not_virtual'        => $order->getIsNotVirtual(),
                'email_customer_note'   => $order->getEmailCustomerNote(),
                'frontend_status_label' => $order->getFrontendStatusLabel()
            ]
        ];
    }

    /**
     * Get the payment info block as HTML.
     *
     * @param Order $order
     *
     * @return string
     */
    private function getPaymentHtml(Order $order)
    {
        return $order->getPayment()->getMethod();
    }

    /**
     * Render a shipping address into HTML.
     *
     * @param Order $order
     *
     * @return string|null
     */
    private function getFormattedShippingAddress($order)
    {
        return $order->getIsVirtual()
            ? null
            : $this->addressRenderer->format($order->getShippingAddress(), 'html');
    }

    /**
     * Render a billing address into HTML.
     *
     * @param Order $order
     *
     * @return string|null
     */
    private function getFormattedBillingAddress($order)
    {
        return $this->addressRenderer->format($order->getBillingAddress(), 'html');
    }

    /**
     * Get template options for the given order.
     *
     * @param Order $order
     *
     * @return array
     */
    private function getTemplateOptions(Order $order): array
    {
        return [
            'area'  => \Magento\Framework\App\Area::AREA_FRONTEND,
            'store' => $order->getStoreId(),
        ];
    }

    /**
     * Get time to next order for the subscription associated with the given order.
     *
     * @param Order $order
     *
     * @return string
     * @throws NoSuchEntityException
     * @throws DateMalformedStringException
     */
    private function getTimeToNextOrder(Order $order): string
    {
        $subscriptionLinkId = $this->subscriptionLinkRepository->getSubscriptionIdFromOrderId($order->getId());
        $subscription = $this->subscriptionRepository->get($subscriptionLinkId);
        if ($subscription->getNextOrderDate()) {
            $nowDate = new DateTime();
            $nowDate->setTime(0, 0);
            $nextDate = new DateTime($subscription->getNextOrderDate());
            $nextDate->setTime(0, 0);
            $interval = $nowDate->diff($nextDate);
            return $interval->format('%a days');
        }

        return $this->recurringConfig->getOrderCreationLeadDays() . ' days';
    }
}
