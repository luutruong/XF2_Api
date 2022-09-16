<?php

namespace Truonglv\Api\Service;

use function trim;
use function count;
use function urldecode;
use function array_replace_recursive;
use Truonglv\Api\Entity\Subscription;

class OneSignal extends AbstractPushNotification
{
    const API_END_POINT = 'https://onesignal.com/api/v1';

    const BADGE_TYPE_SET_TO = 'SetTo';
    const BADGE_TYPE_INCREASE = 'Increase';

    /**
     * @return string
     */
    protected function getProviderId()
    {
        return 'one_signal';
    }

    /**
     * @param mixed $subscriptions
     * @param string $title
     * @param string $body
     * @param array $data
     * @throws \XF\PrintableException
     * @return void
     */
    protected function doSendNotification($subscriptions, $title, $body, array $data)
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

    /**
     * @param string $externalId
     * @param string $pushToken
     * @return void
     * @throws \XF\PrintableException
     */
    public function unsubscribe($externalId, $pushToken)
    {
        $response = null;

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

    /**
     * @return string
     */
    protected function getApiKey()
    {
        return '';
    }

    /**
     * @return string
     */
    protected function getAppId()
    {
        return '';
    }

    /**
     * @param array $options
     * @return \GuzzleHttp\Client
     */
    protected function client(array $options = [])
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
