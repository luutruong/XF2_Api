<?php

namespace Truonglv\Api;

use Truonglv\Api\Entity\Log;
use XF\Api\Controller\AbstractController;

class Listener
{
    public static function onControllerPreDispatch(\XF\Mvc\Controller $controller, $action, \XF\Mvc\ParameterBag $params)
    {
        if (!$controller instanceof AbstractController) {
            return;
        }

        $appVersion = $controller->request()->getServer(App::HEADER_KEY_APP_VERSION);
        App::$enableLogging = !empty($appVersion);
    }

    public static function onAppApiComplete(\XF\Api\App $app, \XF\Http\Response &$response)
    {
        if (!App::$enableLogging) {
            return;
        }

        $request = $app->request();
        /** @var \Truonglv\Api\Repository\Log $logRepo */
        $logRepo = $app->repository('Truonglv\Api:Log');

        /** @var Log $log */
        $log = $app->em()->create('Truonglv\Api:Log');
        $log->user_id = \XF::visitor()->user_id;

        $log->app_version = $request->getServer(App::HEADER_KEY_APP_VERSION);
        $log->user_device = $request->getServer(App::HEADER_KEY_DEVICE_INFO);

        $log->end_point = $request->getRequestUri();
        $log->method = strtoupper($request->getRequestMethod());

        $log->payload = [
            'GET' => $_GET,
            'POST' => $_POST,
        ];

        $log->response_code = $response->httpCode();
        $log->response = $logRepo->prepareDataForLog($response->body());

        $log->save();
    }
}
