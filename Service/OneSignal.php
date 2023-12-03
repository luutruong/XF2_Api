<?php

namespace Truonglv\Api\Service;

use function trim;
use function count;
use GuzzleHttp\Client;
use function urldecode;
use function array_replace_recursive;
use Truonglv\Api\Entity\Subscription;
use XF\Mvc\Entity\AbstractCollection;

class OneSignal extends AbstractPushNotification
{
    const API_END_POINT = 'https://onesignal.com/api/v1';

    const BADGE_TYPE_SET_TO = 'SetTo';
    const BADGE_TYPE_INCREASE = 'Increase';

    protected function getProviderId(): string
    {
        return 'one_signal';
    }

    protected function doSendNotification(AbstractCollection $subscriptions, string $title, string $body, array $data): void
    {
        $playerIds = [];
        /** @var Subscription $subscription */
        foreach ($subscriptions as $subscription) {
            if (trim($subscription->provider_key) !== '') {
                $playerIds[] = $subscription->provider_key;
            }
        }

        if (count($playerIds) === 0) {
            return;
        }

        $payload = [
            'include_player_ids' => $playerIds,
            'app_id' => $this->getAppId(),
            'headings' => [
                'en' => $title
            ],
            'contents' => [
                'en' => $body
            ],
            'data' => $data,
            'ios_badgeType' => self::BADGE_TYPE_INCREASE,
            'ios_badgeCount' => 1
        ];

        $this->sendNotificationRequest('POST', self::API_END_POINT . '/notifications', [
            'json' => $payload
        ]);
    }

    public function unsubscribe(string $externalId, string $pushToken): void
    {
        $endPoint = self::API_END_POINT . '/players/' . urldecode($externalId);
        $payload = [
            'app_id' => $this->getAppId(),
            'notification_types' => -2,
            'identifier' => $pushToken
        ];

        $this->sendNotificationRequest('PUT', $endPoint, [
            'json' => $payload
        ]);
    }

    protected function getApiKey(): string
    {
        return '';
    }

    protected function getAppId(): string
    {
        return '';
    }

    protected function client(array $options = []): Client
    {
        $options = array_replace_recursive($options, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => "Basic: {$this->getApiKey()}"
            ]
        ]);

        return parent::client($options);
    }
}
