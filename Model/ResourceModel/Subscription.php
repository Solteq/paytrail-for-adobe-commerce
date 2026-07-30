<?php

namespace Paytrail\PaymentService\Model\ResourceModel;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Model\ResourceModel\Db\VersionControl\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\VersionControl\RelationComposite;
use Magento\Framework\Model\ResourceModel\Db\VersionControl\Snapshot;
use Paytrail\PaymentService\Api\Data\SubscriptionInterface;
use Paytrail\PaymentService\Model\Recurring\Config;

class Subscription extends AbstractDb
{
    public const PAYTRAIL_SUBSCRIPTIONS_TABLENAME = 'paytrail_subscriptions';

    /**
     * Subscription constructor.
     *
     * @param Context $context
     * @param Snapshot $entitySnapshot
     * @param RelationComposite $entityRelationComposite
     * @param Config $config
     * @param string|null $connectionName
     */
    public function __construct(
        Context $context,
        Snapshot $entitySnapshot,
        RelationComposite $entityRelationComposite,
        private readonly Config $config,
        ?string $connectionName = null
    ) {
        parent::__construct($context, $entitySnapshot, $entityRelationComposite, $connectionName);
    }

    /**
     * Subscription constructor.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(self::PAYTRAIL_SUBSCRIPTIONS_TABLENAME, 'entity_id');
    }

    /**
     * Fetches an array of [ paytrail_subscriptions::entity_id => Sales_order::entity_id ]
     * Consider limiting query results to certain count to allow for better performance when hundreds or thousands of
     * recurring payments exist.
     *
     * @return array
     * @throws LocalizedException
     */
    public function getClonableOrderIds()
    {
        $connection = $this->getConnection();
        $newestOrderIds = $this->getNewestOrderIds(true);

        return $this->filterUnPaidIds($connection, $newestOrderIds);
    }

    /**
     * BeforeSave function
     *
     * @param AbstractModel $object
     *
     * @return $this|Subscription
     * @throws CouldNotSaveException
     */
    protected function _beforeSave(AbstractModel $object)
    {
        if (!$this->canSave($object)) {
            throw new CouldNotSaveException(__('Invalid recurring payment profile'));
        }

        return $this;
    }

    /**
     * CanSave function
     *
     * @param AbstractModel $object
     *
     * @return bool
     * @throws CouldNotSaveException
     */
    private function canSave(AbstractModel $object): bool
    {
        if (!$object->getData('recurring_profile_id')) {
            throw new CouldNotSaveException(__('Cannot save recurring payments without profiles'));
        }

        $connection = $this->getConnection();

        $select = $connection->select()
            ->from('recurring_payment_profiles')
            ->where('profile_id = ?', $object->getData('recurring_profile_id'));

        return !empty($this->getConnection()->fetchRow($select));
    }

    /**
     * Updates subscription status to failed with a direct query.
     *
     * @param int $subscriptionId
     *
     * @return void
     */
    public function forceFailedStatus($subscriptionId)
    {
        $connection = $this->getConnection();

        $connection->update(
            self::PAYTRAIL_SUBSCRIPTIONS_TABLENAME,
            ['status' => SubscriptionInterface::STATUS_FAILED],
            $connection->quoteInto('entity_id = ?', $subscriptionId)
        );
    }

    /**
     * GetNewestOrderIds function
     *
     * @param bool $addDateFilter
     *
     * @return array
     * @throws \DateMalformedStringException
     */
    public function getNewestOrderIds(bool $addDateFilter = false): array
    {
        $select = $this->getConnection()->select();
        $select->from(
            ['sublink' => 'paytrail_subscription_link'],
            [
                'subscription_id' => 'subscription_id',
                'order_id'        => 'MAX(order_id)'
            ]
        );
        $select->join(
            ['sub' => self::PAYTRAIL_SUBSCRIPTIONS_TABLENAME],
            'sub.entity_id = sublink.subscription_id',
            []
        );
        $select->where(
            'sub.status IN (?)',
            SubscriptionInterface::CLONEABLE_STATUSES
        );

        if ($addDateFilter) {
            $date = new \DateTime();
            $date->modify(sprintf('+%d day', $this->config->getOrderCreationLeadDays()));
            $select->where(
                'sub.next_order_date < ?',
                $date->format('Y-m-d H:i:s')
            );
        }

        $select->group('sublink.subscription_id');

        return $this->getConnection()->fetchPairs($select);
    }


    /**
     * FilterUnPaidIds function
     *
     * @param AdapterInterface $connection
     * @param array $newestOrderIds
     *
     * @return array
     */
    private function filterUnPaidIds(AdapterInterface $connection, array $newestOrderIds): array
    {
        $select = $connection->select();
        $select->from(
            ['sublink' => 'paytrail_subscription_link'],
            ['subscription_id', 'order_id']
        );
        $select->where('order_id IN (?)', $newestOrderIds);
        $select->join(
            ['so' => 'sales_order'],
            'so.entity_id = sublink.order_id',
            []
        );
        $select->where(
            'so.grand_total != 0
            AND so.grand_total = so.total_paid'
        );

        return $connection->fetchPairs($select);
    }
}
