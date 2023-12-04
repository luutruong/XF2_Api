<?php

namespace Truonglv\Api\XF\Admin\Controller;

class Tools extends XFCP_Tools
{
    public function actionTApiTestPushNotifications()
    {
        if ($this->isPost()) {
            $input = $this->filter([
                'device_token' => 'str',
                'title' => 'str',
                'message' => 'str',
                'data' => 'str',
            ]);

            $input['data'] = \GuzzleHttp\json_decode($input['data'], true);
        }

        return $this->view(
            'Truonglv\Api:Tools\TestPushNotifications',
            'tapi_test_push_notifications',
            [
            ]
        );
    }
}
