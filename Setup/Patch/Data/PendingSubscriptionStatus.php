<?php

namespace Paytrail\PaymentService\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Sales\Model\Order;

class PendingSubscriptionStatus implements DataPatchInterface
{
    public const string ORDER_STATUS_PENDING_SUBSCRIPTION = 'pending_subscription';


    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->installPaytrailStatus();
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * @return void
     */
    private function installPaytrailStatus(): void
    {
        $statusData = [
            'status' => self::ORDER_STATUS_PENDING_SUBSCRIPTION,
            'label'  => __('Pending Paytrail Subscription')
        ];

        $this->moduleDataSetup->getConnection()->insertOnDuplicate(
            $this->moduleDataSetup->getTable('sales_order_status'),
            $statusData
        );

        $this->addToPendingPaymentState();
    }

    /**
     * @return void
     */
    private function addToPendingPaymentState(): void
    {
        $data = [
            'status'           => self::ORDER_STATUS_PENDING_SUBSCRIPTION,
            'state'            => Order::STATE_PENDING_PAYMENT,
            'is_default'       => 0,
            'visible_on_front' => 1,
        ];

        $this->moduleDataSetup->getConnection()->insertOnDuplicate(
            $this->moduleDataSetup->getTable('sales_order_status_state'),
            $data
        );
    }
}
