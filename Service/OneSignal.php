<?php

namespace Truonglv\Api\Service;

use Truonglv\Api\App;
use XF\Repository\UserAlert;
use XF\Entity\ConversationMessage;
use XF\Entity\ConversationRecipient;
use Truonglv\Api\Entity\Subscription;

class OneSignal extends AbstractPushNotification
{
    const API_END_POINT = 'https://onesignal.com/api/v1';

    const BADGE_TYPE_SET_TO = 'SetTo';
    const BADGE_TYPE_INCREASE = 'Increase';

    /**
     * @var string
     */
    private $appId = '';
    /**
     * @var string
     */
    private $apiKey = '';

    /**
     * @param \XF\Entity\UserAlert $alert
     * @return bool
     */
    public function sendNotification(\XF\Entity\UserAlert $alert)
    {
        $subscriptions = $this->findSubscriptions()
            ->where('user_id', $alert->alerted_user_id)
            ->fetch();
        if (!$subscriptions->count()) {
            return false;
        }

        $playerIds = [];
        /** @var Subscription $subscription */
        foreach ($subscriptions as $subscription) {
            if (\trim($subscription->provider_key) !== '') {
                $playerIds[] = $subscription->provider_key;
            }
        }

        if (\count($playerIds) === 0) {
            return false;
        }

        /** @var UserAlert $alertRepo */
        $alertRepo = $this->app->repository('XF:UserAlert');
        $finder = $alertRepo->findAlertsForUser($alert->alerted_user_id);
        $finder->where('view_date', 0);
        $finder->where('content_type', App::getSupportAlertContentTypes());

        /** @var \Truonglv\Api\XF\Entity\UserAlert $mixed */
        $mixed = $alert;

        $payload = [
            'include_player_ids' => $playerIds,
            'app_id' => $this->appId,
            'headings' => [
                'en' => $this->app->options()->boardTitle
            ],
            'contents' => [
                'en' => $this->getAlertContentBody($alert)
            ],
            'data' => $mixed->getTApiAlertData(true),
            'ios_badgeType' => self::BADGE_TYPE_SET_TO,
            'ios_badgeCount' => $finder->total()
        ];

        $this->sendNotificationRequest('POST', self::API_END_POINT . '/notifications', [
            'json' => $payload
        ]);

        return true;
    }

    /**
     * @param ConversationMessage $message
     * @param string $actionType
     * @return void
     */
    public function sendConversationNotification(ConversationMessage $message, $actionType)
    {
        if (!in_array($actionType, ['create', 'reply'], true)) {
            return;
        }

        $receivers = $message->Conversation->getRelationFinder('Recipients')
            ->where('recipient_state', 'active')
            ->with(['User', 'User.Option'], true)
            ->fetch();
        if (!$receivers->count()) {
            return;
        }

        $sender = $message->User;

        /** @var ConversationRecipient $receiver */
        foreach ($receivers as $receiver) {
            if ($sender->user_id === $receiver->User->user_id) {
                continue;
            }

            $subscriptions = $this->findSubscriptions()
                ->where('user_id', $receiver->User->user_id)
                ->fetch();
            if (!$subscriptions->count()) {
                continue;
            }

            $playerIds = [];
            /** @var Subscription $subscription */
            foreach ($subscriptions as $subscription) {
                if (\trim($subscription->provider_key) !== '') {
                    $playerIds[] = $subscription->provider_key;
                }
            }

            if (\count($playerIds) === 0) {
                continue;
            }

            $payload = [
                'include_player_ids' => $playerIds,
                'app_id' => $this->appId,
                'headings' => [
                    'en' => $this->app->options()->boardTitle
                ],
                'contents' => [
                    'en' => $this->getConversationPushContentBody($receiver->User, $message, $actionType)
                ],
                'data' => [
                    'content_type' => 'conversation_message',
                    'content_id' => $message->message_id
                ],
                'ios_badgeType' => self::BADGE_TYPE_INCREASE,
                'ios_badgeCount' => 1
            ];

            $this->sendNotificationRequest('POST', self::API_END_POINT . '/notifications', [
                'json' => $payload
            ]);
        }
    }

    /**
     * @param string $externalId
     * @param string $pushToken
     * @return void
     */
    public function unsubscribe($externalId, $pushToken)
    {
        $response = null;

        $endPoint = self::API_END_POINT . '/players/' . \urldecode($externalId);
        $payload = [
            'app_id' => $this->appId,
            'notification_types' => -2,
            'identifier' => $pushToken
        ];

        $this->sendNotificationRequest('PUT', $endPoint, [
            'json' => $payload
        ]);
    }

    /**
     * @param string $method
     * @param string $endPoint
     * @param array $payload
     * @return void
     */
    protected function sendNotificationRequest($method, $endPoint, array $payload)
    {
        $response = null;

        try {
            $response = $this->client()->request($method, $endPoint, $payload);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            $this->app->logException($e, false, '[tl] Api: ');
        }

        if ($response === null) {
            return;
        }

        $this->logRequest(
            $method,
            $endPoint,
            $payload,
            $response->getStatusCode(),
            $response->getBody()->getContents()
        );
    }

    /**
     * @return \XF\Mvc\Entity\Finder
     */
    protected function findSubscriptions()
    {
        $finder = parent::findSubscriptions();
        $finder->where('provider', 'one_signal');

        return $finder;
    }

    /**
     * @return \GuzzleHttp\Client
     */
    protected function client()
    {
        return $this->app->http()->createClient([
            'connect_timeout' => 5,
            'timeout' => 5,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => "Basic: {$this->apiKey}"
            ]
        ]);
    }

    /**
     * @return void
     */
    protected function setupDefaults()
    {
        $apiKey = \trim($this->app->options()->tApi_oneSignalApiKey);
        $appId = \trim($this->app->options()->tApi_oneSignalAppId);

        if ($appId === '') {
            throw new \InvalidArgumentException('OneSignal api ID must be set!');
        }
        if ($apiKey === '') {
            throw new \InvalidArgumentException('OneSignal api key must be set!');
        }

        $this->apiKey = $apiKey;
        $this->appId = $appId;
    }
}
