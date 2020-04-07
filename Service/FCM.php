<?php

namespace Truonglv\Api\Service;

use Truonglv\Api\Entity\Subscription;

class FCM extends AbstractPushNotification
{
    const END_POINT = 'https://fcm.googleapis.com/fcm/send';

    /**
     * @return string
     */
    protected function getProviderId()
    {
        return 'fcm';
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
        $ids = [];
        /** @var Subscription $subscription */
        foreach ($subscriptions as $subscription) {
            $ids[] = $subscription->device_token;
        }

        $payload = [
            'notification' => [
                'body' => $body,
                'title' => $title,
            ],
            'data' => $data,
            'content_available' => true,
        ];
        if (\count($ids) > 1) {
            $payload['registration_ids'] = $ids;
        } else {
            $payload['to'] = \reset($ids);
        }

        $this->sendNotificationRequest(
            'POST',
            self::END_POINT,
            [
                'json' => $payload
            ]
        );
    }

    /**
     * @param string $externalId
     * @param string $pushToken
     * @return void
     */
    public function unsubscribe($externalId, $pushToken)
    {
    }

    /**
     * @param array $options
     * @return \GuzzleHttp\Client
     */
    protected function client(array $options = [])
    {
        $apiKey = $this->app->options()->fcmServerKey;
        $options = \array_replace_recursive($options, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => "key={$apiKey}"
            ]
        ]);

        return parent::client($options);
    }
}
