<?php

namespace Truonglv\Api\Job;

use XF\Job\JobResult;
use XF\Job\AbstractJob;
use XF\Entity\UserAlert;
use Truonglv\Api\Entity\AlertQueue;
use Truonglv\Api\Service\OneSignal;
use XF\Timer;

class PushNotification extends AbstractJob
{
    /**
     * @param int $maxRunTime
     *
     * @return JobResult
     * @throws \XF\PrintableException
     */
    public function run($maxRunTime)
    {
        if (empty($this->data['alert_id'])) {
            $timer = new Timer($maxRunTime);

            while (true) {
                $entities = $this->app
                    ->finder('Truonglv\Api:AlertQueue')
                    ->with('UserAlert.Receiver')
                    ->order('alert_id')
                    ->limit(10)
                    ->fetch();

                if (!$entities->count()) {
                    break;
                }

                /** @var AlertQueue $entity */
                foreach ($entities as $entity) {
                    $entity->delete(false);

                    /** @var UserAlert|null $userAlert */
                    $userAlert = $entity->UserAlert;
                    if ($userAlert) {
                        $this->send($userAlert);
                    }

                    if ($timer->limitExceeded()) {
                        break(2);
                    }
                }
            }
        } else {
            /** @var UserAlert|null $userAlert */
            $userAlert = $this->app->em()->find('XF:UserAlert', $this->data['alert_id']);
            if ($userAlert) {
                $this->send($userAlert);
            }
        }

        return $this->complete();
    }

    protected function send(UserAlert $userAlert)
    {
        if ($userAlert->view_date > 0) {
            return;
        }

        /** @var OneSignal $service */
        $service = $this->app->service('Truonglv\Api:OneSignal');
        $service->sendNotification($userAlert);
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
