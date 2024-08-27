<?php

namespace Truonglv\Api\Api\ControllerPlugin;

use XF\Entity\User;
use XF\Api\Result\ArrayResult;
use XF\Api\Mvc\Reply\ApiResult;
use XF\Api\Result\EntityResult;
use XF\Api\Result\EntityResults;
use XF\Entity\ConversationMaster;
use XF\Entity\ConversationRecipient;
use XF\Api\ControllerPlugin\AbstractPlugin;
use XF\Finder\ConversationRecipientFinder;

class ConversationPlugin extends AbstractPlugin
{
    public function includeLastMessage(ApiResult $response): ApiResult
    {
        $apiResult = $response->getApiResult();
        if (!$apiResult instanceof ArrayResult
            || $this->filter('with_last_message', 'bool') !== true
        ) {
            return $response;
        }

        $result = $apiResult->getResult();
        if (isset($result['conversations'])) {
            /** @var EntityResults[] $entityResults */
            $entityResults =& $result['conversations'];
            foreach ($entityResults as $entityResult) {
                $entityResult->includeRelation('LastMessage');
            }

            $apiResult->setResult($result);
            $response->setApiResult($apiResult);
        } elseif (isset($result['conversation'])) {
            /** @var EntityResult $entityResult */
            $entityResult = $result['conversation'];
            
            $entityResult->includeRelation('LastMessage');

            $result['conversation'] = $entityResult;
            $apiResult->setResult($result);
            $response->setApiResult($apiResult);
        }

        return $response;
    }

    public function addRecipientsIntoResult(ApiResult $response): ApiResult
    {
        $apiResult = $response->getApiResult();
        if (!$apiResult instanceof ArrayResult) {
            return $response;
        }

        if ($this->filter('tapi_recipients', 'bool') !== true) {
            return $response;
        }

        $result = $apiResult->getResult();
        if (isset($result['conversation'])) {
            /** @var EntityResult $entityResult */
            $entityResult = $result['conversation'];
            /** @var ConversationMaster $conversation */
            $conversation = $entityResult->getEntity();
            $recipients = $this->getConversationRecipients([$entityResult->getEntity()]);

            $entityResult->includeExtra(
                'tapi_recipients',
                $recipients[$conversation->conversation_id]
            );

            $result['conversation'] = $entityResult;
            $apiResult->setResult($result);
            $response->setApiResult($apiResult);
        } elseif (isset($result['conversations'])) {
            /** @var EntityResults $entityResults */
            $entityResults = $result['conversations'];
            $recipients = $this->getConversationRecipients($entityResults->getEntities());

            $entities = $result['conversations']->getEntityResults();
            foreach ($entities as &$entityResult) {
                /** @var ConversationMaster $conversation */
                $conversation = $entityResult->getEntity();
                $entityResult->includeExtra(
                    'tapi_recipients',
                    $recipients[$conversation->conversation_id]
                );
            }

            $result['conversations'] = $entities;
            $apiResult->setResult($result);
            $response->setApiResult($apiResult);
        }

        return $response;
    }

    protected function getConversationRecipients(array $conversations): array
    {
        $conversationIds = [];
        /** @var ConversationMaster $conversation */
        foreach ($conversations as $conversation) {
            $conversationIds[] = $conversation->conversation_id;
        }

        $recipients = $this->finder(ConversationRecipientFinder::class)
            ->with('User')
            ->where('conversation_id', $conversationIds)
            ->where('recipient_state', 'active')
            ->fetch();
        $conversationRecipients = [];
        /** @var ConversationRecipient $recipient */
        foreach ($recipients as $recipient) {
            if ($recipient->User !== null) {
                $conversationRecipients[$recipient->conversation_id][] = $this->prepareApiDataForUser($recipient->User);
            }
        }

        return $conversationRecipients;
    }

    protected function prepareApiDataForUser(User $user): array
    {
        $data = [
            'user_id' => $user->user_id,
            'username' => $user->username,
            'avatar_urls' => [],
        ];
        foreach (array_keys($this->app->container('avatarSizeMap')) as $avatarSize) {
            $data['avatar_urls'][$avatarSize] = $user->getAvatarUrl($avatarSize, null, true);
        }

        return $data;
    }
}
