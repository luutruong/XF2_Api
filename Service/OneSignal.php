<?php

namespace Truonglv\Api\Service;

use Truonglv\Api\App;
use Truonglv\Api\Entity\Log;
use XF\Repository\UserAlert;
use Truonglv\Api\Entity\Subscription;

class OneSignal extends AbstractPushNotification
{
    const API_END_POINT = 'https://onesignal.com/api/v1';

    private $appId;
    private $apiKey;

    public function send()
    {
        $client = $this->app->http()->client();

        $subscriptions = $this->findSubscriptions()
            ->where('provider', 'one_signal')
            ->fetch();
        if (!$subscriptions->count()) {
            return false;
        }

        $alert = $this->alert;
        $playerIds = [];
        /** @var Subscription $subscription */
        foreach ($subscriptions as $subscription) {
            if ($subscription->provider_key) {
                $playerIds[] = $subscription->provider_key;
            }
        }

        if (empty($playerIds)) {
            return false;
        }

        $html = $alert->render();

        /** @var UserAlert $alertRepo */
        $alertRepo = $this->app->repository('XF:UserAlert');
        $finder = $alertRepo->findAlertsForUser($alert->alerted_user_id);
        $finder->where('view_date', 0);
        $finder->where('content_type', App::getSupportAlertContentTypes());

        $payload = [
            'include_player_ids' => $playerIds,
            'app_id' => $this->appId,
            'headings' => $this->app->options()->boardTitle,
            'contents' => [
                'en' => strip_tags($html)
            ],
            'data' => [
                'content_type' => $alert->content_type,
                'content_id' => $alert->content_id,
                'alert_id' => $alert->alert_id
            ],
            'ios_badgeType' => 'SetTo',
            'ios_badgeCount' => $finder->total()
        ];

        $response = null;

        try {
            $response = $client->post(self::API_END_POINT . '/notifications', [
                'connect_timeout' => 5,
                'timeout' => 5,
                'json' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => "Basic: {$this->apiKey}"
                ]
            ]);
        } catch (\Exception $e) {
            $this->app->logException($e, false, '[tl] Api: ');
        }

        if ($response === null) {
            return false;
        }

        /** @var Log $log */
        $log = $this->app->em()->create('Truonglv\Api:Log');
        $log->payload = $payload;
        $log->app_version = '';
        $log->user_id = 0;
        $log->user_device = '';
        $log->end_point = self::API_END_POINT . '/notifications';
        $log->method = 'POST';
        $log->response_code = $response->getStatusCode();
        $log->response = $response->getBody()->getContents();
        $log->save();

        return true;
    }

    protected function setupDefaults()
    {
        $apiKey = $this->app->options()->tApi_oneSignalApiKey;
        $appId = $this->app->options()->tApi_oneSignalAppId;

        if (empty($appId)) {
            throw new \InvalidArgumentException('OneSignal api ID must be set!');
        }
        if (empty($apiKey)) {
            throw new \InvalidArgumentException('OneSignal api key must be set!');
        }

        $this->apiKey = $apiKey;
        $this->appId = $appId;
    }
}
