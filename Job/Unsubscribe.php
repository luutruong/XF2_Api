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
        if (empty($this->data['provider'])
            || empty($this->data['provider_key'])
            || empty($this->data['device_token'])
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

    public function getStatusMessage()
    {
        return 'Unsubscribe...';
    }

    public function canCancel()
    {
        return false;
    }

    public function canTriggerByChoice()
    {
        return false;
    }
}
