<?php

namespace Truonglv\Api\Job;

use XF\Job\JobResult;
use XF\Job\AbstractJob;
use Truonglv\Api\Service\AbstractPushNotification;

class Unsubscribe extends AbstractJob
{
    /**
     * @param int $maxRunTime
     *
     * @return JobResult
     */
    public function run($maxRunTime)
    {
        if (!isset($this->data['provider'])
            || !isset($this->data['provider_key'])
            || !isset($this->data['device_token'])
        ) {
            return $this->complete();
        }

        /** @var AbstractPushNotification|null $service */
        $service = null;
        if ($this->data['provider'] === 'one_signal') {
            $service = $this->app->service('Truonglv\Api:OneSignal');
        }

        if ($service) {
            $service->unsubscribe($this->data['provider_key'], $this->data['device_token']);
        }

        return $this->complete();
    }

    /**
     * @return string
     */
    public function getStatusMessage()
    {
        return 'Unsubscribe...';
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
