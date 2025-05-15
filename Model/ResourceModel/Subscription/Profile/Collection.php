<?php

namespace Paytrail\PaymentService\Model\ResourceModel\Subscription\Profile;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Paytrail\PaymentService\Api\Data\RecurringProfileSearchResultInterface;
use Paytrail\PaymentService\Model\Subscription\Profile;
use Paytrail\PaymentService\Model\ResourceModel\Subscription\Profile as ProfileResource;

class Collection extends AbstractCollection implements RecurringProfileSearchResultInterface
{
    /** @var SearchCriteriaInterface */
    private $searchCriteria;

    /**
     * Initialize the collection.
     */
    protected function _construct()
    {
        $this->_init(
            Profile::class,
            ProfileResource::class
        );
    }

    /**
     * Set items list.
     *
     * @param \Magento\Framework\DataObject[] $items
     *
     * @return Collection
     * @throws \Exception
     */
    public function setItems(array $items = [])
    {
        if (!$items) {
            return $this;
        }
        foreach ($items as $item) {
            $this->addItem($item);
        }
        return $this;
    }

    /**
     * Search criteria getter.
     *
     * @return SearchCriteriaInterface
     */
    public function getSearchCriteria()
    {
        return $this->searchCriteria;
    }

    /**
     * Search criteria setter.
     *
     * @param SearchCriteriaInterface $searchCriteria
     *
     * @return $this|Collection
     */
    public function setSearchCriteria(SearchCriteriaInterface $searchCriteria)
    {
        $this->searchCriteria = $searchCriteria;

        return $this;
    }

    /**
     * Get total count.
     *
     * @return int
     */
    public function getTotalCount()
    {
        return $this->getSize();
    }

    /**
     * Set total count.
     *
     * @param int $totalCount
     *
     * @return Collection
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function setTotalCount($totalCount)
    {
        // total count is the collections size, do not modify it.
        return $this;
    }
}
