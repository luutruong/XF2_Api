<?php

namespace Truonglv\Api\Repository;

use XF\Mvc\Entity\Repository;
use XF\Mvc\Entity\ArrayCollection;

class AlertQueue extends Repository
{
    /**
     * @param string $contentType
     * @param int $contentId
     * @param array $payload
     * @param null|int $queueDate
     * @return void
     */
    public static function queue($contentType, $contentId, array $payload = [], $queueDate = null)
    {
        /** @var static $repo */
        $repo = \XF::app()->repository('Truonglv\Api:AlertQueue');

        $repo->insertQueue($contentType, $contentId, $payload, $queueDate);
    }

    /**
     * @param ArrayCollection $queues
     * @return void
     */
    public function addContentIntoQueues(ArrayCollection $queues)
    {
        if (!$queues->count()) {
            return;
        }

        $contentMap = [];
        /** @var \Truonglv\Api\Entity\AlertQueue $queue */
        foreach ($queues as $queue) {
            $contentMap[$queue->content_type][$queue->content_id] = true;
        }

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
            $entities = isset($contents[$queue->content_type])
                ? $contents[$queue->content_type]
                : [];
            if (isset($entities[$queue->content_id])) {
                $content = $entities[$queue->content_id];
            }

            $queue->setContent($content);
        }
    }

    /**
     * @param string $contentType
     * @param int $contentId
     * @param array $payload
     * @param null|int $queueDate
     * @throws \XF\PrintableException
     * @return void
     */
    public function insertQueue($contentType, $contentId, array $payload = [], $queueDate = null)
    {
        if (!$this->app()->options()->tApi_delayPushNotifications) {
            $payload = array_replace($payload, [
                'content_type' => $contentType,
                'content_id' => $contentId
            ]);

            $this->app()
                ->jobManager()
                ->enqueueUnique(
                    'tApi_PN_' . $contentType . $contentId,
                    'Truonglv\Api:PushNotification',
                    $payload
                );

            return;
        }

        /** @var \Truonglv\Api\Entity\AlertQueue $entity */
        $entity = $this->em->create('Truonglv\Api:AlertQueue');
        $entity->content_type = $contentType;
        $entity->content_id = $contentId;
        $entity->payload = $payload;
        $entity->queue_date = $queueDate ?: \XF::$time;

        try {
            $entity->save(false);
        } catch (\XF\Db\DuplicateKeyException $e) {
        }
    }
}
