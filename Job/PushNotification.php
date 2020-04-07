<?php

namespace Truonglv\Api\Job;

use XF\Timer;
use Truonglv\Api\App;
use XF\Job\JobResult;
use XF\Job\AbstractJob;
use XF\Entity\UserAlert;
use XF\Entity\ConversationMessage;
use Truonglv\Api\Entity\AlertQueue;
use Truonglv\Api\Service\AbstractPushNotification;

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
        if (isset($this->data['content_type']) && $this->data['content_type'] === 'alert') {
            /** @var UserAlert|null $userAlert */
            $userAlert = $this->app->em()->find('XF:UserAlert', $this->data['content_id']);
            if ($userAlert) {
                $this->sendAlertNotification($userAlert);
            }
        } elseif (isset($this->data['content_type']) && $this->data['content_type'] === 'conversation_message') {
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
                        /** @var UserAlert $mixed */
                        $mixed = $entity->Content;
                        $this->sendAlertNotification($mixed);
                    } elseif ($entity->content_type === 'conversation_message') {
                        /** @var ConversationMessage $mixed */
                        $mixed = $entity->Content;
                        $this->sendConversationNotification($mixed, $entity->payload['action']);
                    }

                    if ($timer->limitExceeded()) {
                        break(2);
                    }
                }
            }
        }

        return $this->complete();
    }

    /**
     * @param UserAlert $userAlert
     * @return void
     */
    protected function sendAlertNotification(UserAlert $userAlert)
    {
        if ($userAlert->view_date > 0) {
            return;
        }

        /** @var AbstractPushNotification $service */
        $service = $this->app->service(App::$defaultPushNotificationService);
        $service->sendNotification($userAlert);
    }

    /**
     * @param ConversationMessage $message
     * @param string $actionType
     * @return void
     * @throws \XF\PrintableException
     */
    protected function sendConversationNotification(ConversationMessage $message, $actionType)
    {
        /** @var AbstractPushNotification $service */
        $service = $this->app->service(App::$defaultPushNotificationService);
        $service->sendConversationNotification($message, $actionType);
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
