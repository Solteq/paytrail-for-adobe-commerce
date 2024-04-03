<?php

namespace Paytrail\PaymentService\Model\Subscription;

use Carbon\Carbon;
use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Paytrail\PaymentService\Api\Data\RecurringProfileInterface;
use Paytrail\PaymentService\Api\RecurringProfileRepositoryInterface;

class NextDateCalculator
{
    /**
     * @var RecurringProfileRepositoryInterface $profileRepo
     */
    private $profileRepo;
    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var RecurringProfileInterface[]
     */
    private $profiles = [];

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var bool
     */
    private bool $forceWeekdays;

    /**
     * @param RecurringProfileRepositoryInterface $profileRepository
     * @param SerializerInterface $serializer
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        RecurringProfileRepositoryInterface $profileRepository,
        SerializerInterface                 $serializer,
        ScopeConfigInterface                $scopeConfig
    ) {
        $this->profileRepo = $profileRepository;
        $this->serializer  = $serializer;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Calculate next date for a profile.
     *
     * @param int $profileId
     * @param string $startDate
     *
     * @return string
     * @throws NoSuchEntityException
     * @throws Exception
     */
    public function getNextDate($profileId, $startDate = 'now')
    {
        $profile = $this->getProfileById($profileId);

        return $this->calculateNextDate($profile->getSchedule(), $startDate);
    }

    /**
     * Calculate next date based on the schedule.
     *
     * @param string $schedule
     * @param string $startDate
     *
     * @return string
     * @throws Exception
     */
    protected function calculateNextDate($schedule, $startDate)
    {
        $schedule   = $this->serializer->unserialize($schedule);
        $carbonDate = $startDate === 'now' ? Carbon::now() : Carbon::createFromFormat('Y-m-d H:i:s', $startDate);

        switch ($schedule['unit']) {
            case 'D':
                $nextDate = $carbonDate->addDays($schedule['interval']);
                break;
            case 'W':
                $nextDate = $carbonDate->addWeeks($schedule['interval']);
                break;
            case 'M':
                $nextDate = $this->addMonthsNoOverflow($carbonDate, $schedule['interval']);
                break;
            case 'Y':
                $nextDate = $carbonDate->addYearsNoOverflow($schedule['interval']);
                break;
            default:
                throw new LocalizedException(__('Schedule type not supported'));
        }

        if ($this->isForceWeekdays()) {
            $nextDate = $this->getNextWeekday($nextDate);
        }

        return $nextDate->format('Y-m-d');
    }

    /**
     * Get profile by id
     *
     * @param int $profileId
     *
     * @return RecurringProfileInterface
     * @throws NoSuchEntityException
     */
    protected function getProfileById($profileId): RecurringProfileInterface
    {
        if (!isset($this->profiles[$profileId])) {
            $this->profiles[$profileId] = $this->profileRepo->get($profileId);
        }

        return $this->profiles[$profileId];
    }

    /**
     * Get force weekdays config
     *
     * @return bool
     */
    protected function isForceWeekdays()
    {
        if (!isset($this->forceWeekdays)) {
            $this->forceWeekdays = $this->scopeConfig->isSetFlag('sales/recurring_payment/force_weekdays');
        }
        return $this->forceWeekdays;
    }

    /**
     * Get next weekday
     *
     * @param Carbon $nextDate
     *
     * @return Carbon
     */
    private function getNextWeekday($nextDate)
    {
        $newCarbonDate = new Carbon($nextDate);
        if (!$newCarbonDate->isWeekday()) {
            $newCarbonDate = $newCarbonDate->nextWeekday();
            if ($nextDate->format('m') != $newCarbonDate->format('m')) {
                $newCarbonDate = $newCarbonDate->previousWeekday();
            }
        }
        return $newCarbonDate;
    }

    /**
     * Add months no overflow
     *
     * @param Carbon $carbonDate
     * @param int $interval
     *
     * @return Carbon
     */
    protected function addMonthsNoOverflow($carbonDate, $interval)
    {
        $isLastOfMonth = $carbonDate->isLastOfMonth();
        $nextDate      = $carbonDate->addMonthsNoOverflow($interval);

        // adjust date to match the last day of month if the previous date was also last date of month.
        if ($isLastOfMonth) {
            $nextDate->endOfMonth();
        }

        return $nextDate;
    }
}
