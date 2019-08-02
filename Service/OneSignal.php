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

    public function sendNotification(\XF\Entity\UserAlert $alert)
    {
        $subscriptions = $this->findSubscriptions()
            ->where('user_id', $alert->alerted_user_id)
            ->where('provider', 'one_signal')
            ->fetch();
        if (!$subscriptions->count()) {
            return false;
        }

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
            $response = $this->client()->post(self::API_END_POINT . '/notifications', [
                'json' => $payload
            ]);
        } catch (\Exception $e) {
            $this->app->logException($e, false, '[tl] Api: ');
        }

        if ($response === null) {
            return false;
        }

        $this->logRequest(
            'post',
            self::API_END_POINT . '/notifications',
            $payload,
            $response->getStatusCode(),
            $response->getBody()->getContents()
        );

        return true;
    }

    public function unsubscribe($externalId, $pushToken)
    {
        $response = null;

        $endPoint = self::API_END_POINT . '/players/' . urldecode($externalId);
        $payload = [
            'app_id' => $this->appId,
            'notification_types' => -2,
            'identifier' => $pushToken
        ];

        try {
            $response = $this->client()->put($endPoint, [
                'form_params' => $payload
            ]);
        } catch (\Exception $e) {
            \XF::logException($e, false, '[tl] Api: ');
        }

        if (!$response) {
            return;
        }

        $this->logRequest(
            'put',
            $endPoint,
            $payload,
            $response->getStatusCode(),
            $response->getBody()->getContents()
        );
    }

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
