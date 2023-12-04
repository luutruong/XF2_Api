<?php

namespace Truonglv\Api\Service;

use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use XF;
use Throwable;
use XF\Entity\User;
use function strlen;
use function strval;
use function file_exists;
use function is_readable;
use Kreait\Firebase\Factory;
use InvalidArgumentException;
use function file_get_contents;
use Truonglv\Api\Entity\Subscription;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FirebaseCloudMessaging extends AbstractPushNotification
{
    protected function getProviderId(): string
    {
        return 'fcm';
    }

    protected function doSendNotification(XF\Mvc\Entity\AbstractCollection $subscriptions, string $title, string $body, array $data): void
    {
        $fbConfigFile = $this->app->config('tApi_firebaseConfigPath');
        if ($fbConfigFile === null) {
            $fbConfigFile = $this->app->options()->tApi_firebaseConfigPath;
        }
        if (strlen($fbConfigFile) === 0) {
            return;
        }

        if (!file_exists($fbConfigFile) || !is_readable($fbConfigFile)) {
            throw new InvalidArgumentException('Firebase config file not exists or not readable.');
        }

        $contents = file_get_contents($fbConfigFile);
        if ($contents === false) {
            throw new InvalidArgumentException('Cannot read Firebase config.');
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
            $dataTransformed[strval($key)] = strval($value);
        }

        /** @var Subscription $subscription */
        foreach ($subscriptions as $subscription) {
            /** @var User $receiver */
            $receiver = $subscription->User;
            $message = CloudMessage::withTarget('token', $subscription->device_token)
                ->withNotification(Notification::create($title, $body))
                ->withData($dataTransformed);
            if ($subscription->device_type === 'ios') {
                $apnsConfig = ApnsConfig::new();
                $apnsConfig->withApsField('badge', $this->getTotalUnviewedNotifications($receiver))
                    ->withDefaultSound();

                $message = $message->withApnsConfig($apnsConfig);
            } elseif ($subscription->device_type === 'android') {
                $androidConfig = AndroidConfig::fromArray([
                    'notification_count' => $this->getTotalUnviewedNotifications($receiver),
                ]);
                $androidConfig->withDefaultSound()->withDefaultNotificationPriority();
                $message = $message->withAndroidConfig($androidConfig);
            }

            $messages[] = $message;
        }

        $messaging = $factory->createMessaging();
        $this->app->error()->logError(\sprintf('send push notifications: %d', \count($messages)));

        try {
            $messaging->sendAll($messages);
        } catch (Throwable $e) {
            XF::logException($e, false, '[tl] Api: failed to send messages ');
        }
    }

    public function unsubscribe(string $externalId, string $pushToken): void
    {
    }
}
