<?php

namespace Truonglv\Api\Job;

use XF\Entity\ConversationMessage;
use XF\Timer;
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
     * @throws \XF\PrintableException
     */
    public function run($maxRunTime)
    {
        if (!empty($this->data['content_type']) && $this->data['content_type'] === 'alert') {
            /** @var UserAlert|null $userAlert */
            $userAlert = $this->app->em()->find('XF:UserAlert', $this->data['content_id']);
            if ($userAlert) {
                $this->sendAlertNotification($userAlert);
            }
        } elseif (!empty($this->data['content_type']) && $this->data['content_type'] === 'conversation_message') {
            /** @var ConversationMessage|null $convoMessage */
            $convoMessage = $this->app->em()->find('XF:ConversationMessage', $this->data['content_id']);
            if ($convoMessage) {
                $this->sendConversationNotification($convoMessage, $this->data['action']);
            }
        } else {
            $timer = new Timer($maxRunTime);
            /** @var \Truonglv\Api\Repository\AlertQueue $alertQueueRepo */
            $alertQueueRepo = $this->app->repository('Truonglv\Api:AlertQueue');

            while (true) {
                $entities = $this->app
                    ->finder('Truonglv\Api:AlertQueue')
                    ->order('queue_date')
                    ->limit(10)
                    ->fetch();
                $alertQueueRepo->addContentIntoQueues($entities);

                if (!$entities->count()) {
                    break;
                }

                /** @var AlertQueue $entity */
                foreach ($entities as $entity) {
                    $entity->delete(false);

                    if (!$entity->Content) {
                        continue;
                    }

                    if ($entity->content_type === 'alert') {
                        $this->sendAlertNotification($entity->Content);
                    } elseif ($entity->content_type === 'conversation_message') {
                        $this->sendConversationNotification($entity->Content, $entity->payload['action']);
                    }

                    if ($timer->limitExceeded()) {
                        break(2);
                    }
                }
            }
        }

        return $this->complete();
    }

    protected function sendAlertNotification(UserAlert $userAlert)
    {
        if ($userAlert->view_date > 0) {
            return;
        }

        /** @var OneSignal $service */
        $service = $this->app->service('Truonglv\Api:OneSignal');
        $service->sendNotification($userAlert);
    }

    protected function sendConversationNotification(ConversationMessage $message, $actionType)
    {
        /** @var OneSignal $service */
        $service = $this->app->service('Truonglv\Api:OneSignal');
        $service->sendConversationNotification($message, $actionType);
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
