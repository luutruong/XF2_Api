<?php

namespace Truonglv\Api\XF\Admin\Controller;

use Truonglv\Api\App;
use Truonglv\Api\Entity\Subscription;
use Truonglv\Api\Service\FirebaseCloudMessagingService;

class ToolsController extends XFCP_ToolsController
{
    public function actionTApiTestPushNotifications()
    {
        if ($this->isPost()) {
            $input = $this->filter([
                'device_type' => 'str',
                'device_token' => 'str',
                'title' => 'str',
                'message' => 'str',
                'data' => 'str',
            ]);

            $input['data'] = strlen($input['data']) > 0 ? \GuzzleHttp\Utils::jsonDecode($input['data'], true) : [];
            $visitor = \XF::visitor();

            $fcm = $this->app->service(FirebaseCloudMessagingService::class);
            $sub = $this->em()->instantiateEntity(Subscription::class, [
                'subscription_id' => \time(),
                'user_id' => $visitor->user_id,
                'username' => $visitor->username,
                'app_version' => '',
                'device_token' => $input['device_token'],
                'provider' => 'fcm',
                'device_type' => $input['device_type'],
            ]);

            $reflection = new \ReflectionClass($fcm);
            $method = $reflection->getMethod('doSendNotification');
            $method->setAccessible(true);

            $method->invoke($fcm, $this->em()->getBasicCollection([$sub]), $input['title'], $input['message'], $input['data']);

            return $this->redirect(
                $this->buildLink('tools/tapi-test-push-notifications', null, [
                    'success' => 1,
                ]),
            );
        }

        return $this->view(
            'Truonglv\Api:Tools\TestPushNotifications',
            'tapi_test_push_notifications',
            [
            ]
        );
    }
}
