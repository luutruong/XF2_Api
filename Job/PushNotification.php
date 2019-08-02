<?php

namespace Truonglv\Api\Job;

use XF\Job\JobResult;
use XF\Job\AbstractJob;
use XF\Entity\UserAlert;
use Truonglv\Api\Entity\AlertQueue;
use Truonglv\Api\Service\OneSignal;

class PushNotification extends AbstractJob
{
    /**
     * @param int $maxRunTime
     *
     * @return JobResult
     */
    public function run($maxRunTime)
    {
        $entities = $this->app
            ->finder('Truonglv\Api:AlertQueue')
            ->with('UserAlert.Receiver')
            ->order('alert_id')
            ->limit(50)
            ->fetch();
        if (!$entities->count()) {
            return $this->complete();
        }

        $start = microtime(true);
        /** @var AlertQueue $entity */
        foreach ($entities as $entity) {
            $entity->delete(false);

            /** @var UserAlert|null $userAlert */
            $userAlert = $entity->UserAlert;
            if (!$userAlert || $userAlert->view_date > 0) {
                continue;
            }

            /** @var OneSignal $service */
            $service = $this->app->service('Truonglv\Api:OneSignal');
            $service->sendNotification($userAlert);

            if ($maxRunTime && (microtime(true) - $start) >= $maxRunTime) {
                break;
            }
        }

        return $this->complete();
    }

    public function getStatusMessage()
    {
        return 'Sending notifications...';
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
