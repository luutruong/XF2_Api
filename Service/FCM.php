<?php

namespace Truonglv\Api\Service;

use XF\Entity\User;
use Kreait\Firebase\Factory;
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
        $fbConfigFile = $this->app->config('tApi_firebaseConfigPath');
        if ($fbConfigFile === null) {
            $fbConfigFile = $this->app->options()->tApi_firebaseConfigPath;
        }
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

        $messages = [];
        $dataTransformed = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (count($value) === 0) {
                    continue;
                }

                $value = json_encode($value);
            }
            $dataTransformed[\strval($key)] = \strval($value);
        }

        /** @var Subscription $subscription */
        foreach ($subscriptions as $subscription) {
            /** @var User $receiver */
            $receiver = $subscription->User;
            // @phpstan-ignore-next-line
            $message = CloudMessage::withTarget('token', $subscription->device_token)
                // @phpstan-ignore-next-line
                ->withNotification(Notification::create($title, $body))
                ->withData($dataTransformed);
            if ($subscription->device_type === 'ios') {
                $message = $message->withApnsConfig([
                    'payload' => [
                        'aps' => [
                            'badge' => $this->getTotalUnviewedNotifications($receiver),
                            'sound' => 'default',
                        ]
                    ]
                ]);
            } elseif ($subscription->device_type === 'android') {
                /** @var mixed $androidConfig */
                $androidConfig = [
                    'notification' => [
                        'notification_count' => $this->getTotalUnviewedNotifications($receiver),
                        'sound' => 'default',
                    ],
                ];
                $message = $message->withAndroidConfig($androidConfig);
            }

            $messages[] = $message;
        }

        $messaging = $factory->createMessaging();

        try {
            // @phpstan-ignore-next-line
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
