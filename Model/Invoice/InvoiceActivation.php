<?php
declare(strict_types=1);


namespace Paytrail\PaymentService\Model\Invoice;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Paytrail\PaymentService\Model\ConfigProvider;

/**
 * Class InvoiceActivate
 */
class InvoiceActivation
{
    private const ACTIVE_INVOICE_CONFIG_PATH = 'payment/paytrail/activate_invoices_separately';
    public const SUB_METHODS_WITH_MANUAL_ACTIVATION_SUPPORT = [
        'collectorb2c',
        'collectorb2b',
    ];

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var string[]
     */
    private array $activationOverride;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param string[] $activationOverride
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        OrderRepositoryInterface $orderRepository,
        array $activationOverride = []
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->activationOverride = $activationOverride;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Conditionally sets manual invoice activation flag to payment request based on admin configuration
     *
     * @param \Paytrail\SDK\Request\PaymentRequest $paytrailPayment
     * @param string $method
     * @return \Paytrail\SDK\Request\PaymentRequest
     */
    public function setManualInvoiceActivationFlag(&$paytrailPayment, $method)
    {
        // TODO check for virtual products before adding the flag.
        if ($this->canUseManualInvoiceActivation()
            && in_array($method, $this->getInvoiceMethods())) {
            $paytrailPayment->setManualInvoiceActivation(true);
        }

        return $paytrailPayment;
    }

    /**
     * Get admin config for invoice activation
     *
     * @return bool
     */
    private function canUseManualInvoiceActivation(): bool
    {
        return (bool)$this->scopeConfig->getValue(
            self::ACTIVE_INVOICE_CONFIG_PATH,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Return invoice methods that support manual activation flag. Includes dependency injection extension point.
     *
     * @return string[]
     */
    private function getInvoiceMethods(): array
    {
        return array_merge(self::SUB_METHODS_WITH_MANUAL_ACTIVATION_SUPPORT, $this->activationOverride);
    }

    /**
     * Process order when response status='ok'.
     *
     * @param string $responseStatus
     * @param Order $order
     * @return void
     */
    public function processInvoiceActivationResponse($responseStatus, $order)
    {
        if ($responseStatus === 'ok') {
            $order->setState(Order::STATE_COMPLETE);
            $order->setStatus(Order::STATE_COMPLETE);
            $order->setTotalPaid($order->getGrandTotal());
            $order->addCommentToStatusHistory(__('Payment has been completed'));
            $this->orderRepository->save($order);
        }
    }
}
