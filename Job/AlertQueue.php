<?php

namespace Truonglv\Api\Job;

use XF\Job\AbstractJob;
use Truonglv\Api\Repository\AlertQueueRepository;

class AlertQueue extends AbstractJob
{
    /**
     * @inheritDoc
     */
    public function run($maxRunTime)
    {
        $alertQueueRepo = $this->app->repository(AlertQueueRepository::class);
        $alertQueueRepo->run(max($maxRunTime, 0));

        $nextRunTime = $alertQueueRepo->getFirstRunTime();
        if ($nextRunTime > 0) {
            $resume = $this->resume();
            $resume->continueDate = $nextRunTime;

            return $resume;
        }

        return $this->complete();
    }

    /**
     * @return string
     */
    public function getStatusMessage()
    {
        return 'Sending notifications...';
    }

    /**
     * @return bool
     */
    public function canCancel()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function canTriggerByChoice()
    {
        return false;
    }
}
