<?php

namespace Truonglv\Api\Service;

use Truonglv\Api\App;
use Kreait\Firebase\Factory;
use XF\Repository\UserAlert;
use Truonglv\Api\Entity\Subscription;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FCM extends AbstractPushNotification
{
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
        $fbConfigFile = $this->app->options()->tApi_firebaseConfigPath;
        if (\strlen($fbConfigFile) === 0) {
            return;
        }

        if (!\file_exists($fbConfigFile) || !\is_readable($fbConfigFile)) {
            throw new \InvalidArgumentException('Firebase config file not exists or not readable.');
        }

        $contents = \file_get_contents($fbConfigFile);
        if ($contents === false) {
            throw new \InvalidArgumentException('Cannot read Firebase config.');
        }
        $factory = new Factory();
        $factory = $factory->withServiceAccount($contents);

        $badges = [];
        $messages = [];
        $dataTransformed = [];
        foreach ($data as $key => $value) {
            $dataTransformed[\strval($key)] = \strval($value);
        }

        /** @var UserAlert $alertRepo */
        $alertRepo = \XF::app()->repository('XF:UserAlert');

        /** @var Subscription $subscription */
        foreach ($subscriptions as $subscription) {
            if (!\array_key_exists($subscription->user_id, $badges)) {
                $badges[$subscription->user_id] = $alertRepo->findAlertsForUser($subscription->user_id)
                    ->where('view_date', 0)
                    ->where('content_type', App::getSupportAlertContentTypes())
                    ->total();
            }

            $message = CloudMessage::withTarget('token', $subscription->device_token)
                ->withNotification(Notification::create($title, $body))
                ->withData($dataTransformed);
            if ($subscription->device_type === 'ios') {
                $message = $message->withApnsConfig([
                    'payload' => [
                        'aps' => [
                            'badge' => $badges[$subscription->user_id],
                        ]
                    ]
                ]);
            } elseif ($subscription->device_type === 'android') {
                $message = $message->withAndroidConfig([
                    'notification' => [
                        'notification_count' => $badges[$subscription->user_id],
                    ],
                ]);
            }

            \array_push($messages, $message);
        }

        $messaging = $factory->createMessaging();

        try {
            $messaging->sendAll($messages);
        } catch (\Exception $e) {
            \XF::logException($e, false, '[tl] Api: ');
        }
    }

    /**
     * @param string $externalId
     * @param string $pushToken
     * @return void
     */
    public function unsubscribe($externalId, $pushToken)
    {
    }
}
