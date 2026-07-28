<?php

namespace Paytrail\PaymentService\Console\Command;

use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Paytrail\PaymentService\Model\Recurring\RecurringOrderCloner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Notify extends Command
{
    /**
     * Constructor
     *
     * @param RecurringOrderCloner $recurringOrderCloner
     * @param State $state
     */
    public function __construct(
        private readonly RecurringOrderCloner $recurringOrderCloner,
        private readonly State $state
    ) {
        parent::__construct();
    }

    /**
     * Configure
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('paytrail:recurring:notify');
        $this->setDescription('Send recurring payment notification emails.');
    }

    /**
     * Execute
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws LocalizedException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_CRONTAB);
        $this->recurringOrderCloner->process();

        return Cli::RETURN_SUCCESS;
    }
}
