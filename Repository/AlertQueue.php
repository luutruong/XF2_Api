<?php

namespace Truonglv\Api\Repository;

use XF\Timer;
use Truonglv\Api\App;
use XF\Entity\UserAlert;
use XF\Mvc\Entity\Repository;
use XF\Entity\ConversationMessage;
use XF\Mvc\Entity\AbstractCollection;
use Truonglv\Api\Service\AbstractPushNotification;

class AlertQueue extends Repository
{
    public function getSupportedAlertContentTypes(): array
    {
        return [
            'conversation',
            'conversation_message',
            'post',
            'thread',
            'user',
            'trophy',
        ];
    }

    public function getFirstRunTime(): int
    {
        return (int) $this->db()->fetchOne('
            SELECT MIN(`queue_date`)
            FROM `xf_tapi_alert_queue`
        ');
    }

    public function scheduleNextRun(): void
    {
        $runTime = $this->getFirstRunTime();
        if ($runTime <= 0) {
            return;
        }

        $this->app()->jobManager()
            ->enqueueLater(
                'tapi_alertQueue',
                $runTime,
                'Truonglv\Api:AlertQueue'
            );
    }

    public function run(int $maxRunTime): void
    {
        $timer = $maxRunTime > 0 ? new Timer($maxRunTime) : null;
        $records = $this->finder('Truonglv\Api:AlertQueue')
            ->where('queue_date', '<=', \XF::$time)
            ->order('queue_date')
            ->limit($timer === null ? null : 20)
            ->fetch();

        if ($records->count() === 0) {
            return;
        }

        /** @var \Truonglv\Api\Entity\AlertQueue $record */
        foreach ($records as $record) {
            $delete = $this->db()->delete(
                'xf_tapi_alert_queue',
                'content_type = ? AND content_id = ?',
                [$record->content_type, $record->content_id]
            );
            if ($delete <= 0) {
                continue;
            }

            try {
                $this->runQueueEntry($record->content_type, $record->content_id, $record->payload);
            } catch (\Throwable $e) {
            }

            if ($timer !== null && $timer->limitExceeded()) {
                break;
            }
        }
    }

    public function runQueueEntry(string $contentType, int $contentId, array $data = []): void
    {
        switch ($contentType) {
            case 'alert':
                /** @var UserAlert|null $userAlert */
                $userAlert = $this->em->find('XF:UserAlert', $contentId);
                if ($userAlert === null || $userAlert->view_date > 0) {
                    return;
                }

                /** @var AbstractPushNotification $service */
                $service = $this->app()->service(App::$defaultPushNotificationService);
                $service->sendNotification($userAlert);

                break;
            case 'conversation_message':
                if (!isset($data['action'])) {
                    throw new \LogicException('Must be specified `action`');
                }
                /** @var ConversationMessage|null $convoMessage */
                $convoMessage = $this->em->find('XF:ConversationMessage', $contentId);
                if ($convoMessage !== null) {
                    /** @var AbstractPushNotification $service */
                    $service = $this->app()->service(App::$defaultPushNotificationService);
                    $service->sendConversationNotification($convoMessage, $data['action']);
                }

                break;
            default:
                throw new \LogicException('Must be implemented!');
        }
    }

    /**
     * @param AbstractCollection $queues
     * @return void
     */
    public function addContentIntoQueues(AbstractCollection $queues)
    {
        if ($queues->count() < 1) {
            return;
        }

        $contentMap = [];
        /** @var \Truonglv\Api\Entity\AlertQueue $queue */
        foreach ($queues as $queue) {
            $contentMap[$queue->content_type][$queue->content_id] = true;
        }

        /** @var mixed $contents */
        $contents = [];
        foreach ($contentMap as $contentType => $contentIds) {
            if ($contentType === 'alert') {
                $contents[$contentType] = $this->em->findByIds('XF:UserAlert', $contentIds, 'Receiver');
            } elseif ($contentType === 'conversation_message') {
                $contents[$contentType] = $this->em->findByIds('XF:ConversationMessage', $contentIds);
            }
        }

        /** @var \Truonglv\Api\Entity\AlertQueue $queue */
        foreach ($queues as $queue) {
            $content = null;
            $entities = $contents[$queue->content_type] ?? [];
            if (isset($entities[$queue->content_id])) {
                $content = $entities[$queue->content_id];
            }

            $queue->setContent($content);
        }
    }

    public function insertQueue(string $contentType, int $contentId, array $payload = [], ?int $queueDate = null): void
    {
        if ($queueDate <= 0) {
            $queueDate = \XF::$time;
        }
        $delayed = $this->app()->options()->tApi_delayPushNotifications == 1;
        if ($delayed) {
            $this->db()->insert('xf_tapi_alert_queue', [
                'content_type' => $contentType,
                'content_id' => $contentId,
                'payload' => json_encode($payload),
                'queue_date' => $queueDate,
            ], false, '
                `payload` = VALUES(`payload`),
                `queue_date` = VALUES(`queue_date`)
            ');

            $this->scheduleNextRun();

            return;
        }

        $this->runQueueEntry($contentType, $contentId, $payload);
    }
}
