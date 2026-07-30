<?php

namespace Paytrail\PaymentService\Model\Subscription;

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
     *
     */
    public function __construct(
        private readonly TransportBuilder $transportBuilder,
        private readonly Emulation $emulation,
        private readonly Renderer $addressRenderer,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger,
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly SubscriptionLinkRepositoryInterface $subscriptionLinkRepository,
        private readonly Config $recurringConfig
    ) {

    }

    /**
     * @param Order[] $clonedOrders
     */
    public function sendNotifications(array $clonedOrders)
    {
        foreach ($clonedOrders as $order) {
            $this->notify($order);
        }
    }

    /**
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
     * @param Order $order
     *
     * @return string[]
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
     * Get payment info block as html
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
     * Render shipping address into html.
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
     * Render billing address into html.
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
     */
    private function getTimeToNextOrder(Order $order): string
    {
        $subscriptionLink = $this->subscriptionLinkRepository->getSubscriptionIdFromOrderId($order->getId());
        $subscription = $this->subscriptionRepository->get($subscriptionLink->getSubscriptionId());
        $nextOrderDate = $subscription->getNextOrderDate();
        if ($nextOrderDate) {
            $nowDate = new \DateTime();
            $interval = $nowDate->diff($nextOrderDate);
            return $interval->format('%a days');
        }
        return $this->recurringConfig->getOrderCreationLeadDays() . ' days';
    }
}
