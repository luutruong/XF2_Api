<?php

namespace Truonglv\Api\Job;

use Truonglv\Api\Entity\AlertQueue;
use Truonglv\Api\Service\OneSignal;
use XF\Entity\UserAlert;
use XF\Job\AbstractJob;
use XF\Job\JobResult;

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
            $service = $this->app->service('Truonglv\Api:OneSignal', $userAlert);
            $service->send();

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
