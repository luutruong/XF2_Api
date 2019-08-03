<?php

namespace Truonglv\Api;

use XF\Container;
use XF\Entity\ApiKey;
use Truonglv\Api\Entity\Log;
use Truonglv\Api\Entity\AccessToken;
use XF\Api\Controller\AbstractController;

class Listener
{
    public static function appApiSetup(\XF\Api\App $app)
    {
        $app->container()->set('request', function (Container $c) {
            $request = new Http\Request($c['inputFilterer']);
            $request->setCookiePrefix($c['config']['cookie']['prefix']);

            return $request;
        });
    }

    public static function appApiValidateRequest(\XF\Http\Request $request, &$result, &$error, &$code)
    {
        $app = \XF::app();

        $apiKey = $request->getApiKey();
        $ourKey = $app->options()->tApi_apiKey;

        if (!empty($ourKey)) {
            /** @var ApiKey|null $apiKeyEntity */
            $apiKeyEntity = $app->em()->find('XF:ApiKey', $ourKey['apiKeyId']);
            if (!$apiKeyEntity) {
                return;
            }

            if ($apiKey === $apiKeyEntity->api_key) {
                // DO NOT allow request with api_key in header.
                $error = 'api_error.api_key_not_found';
                $code = 401;
                $result = false;

                return;
            }
        }

        $ourApiKey = $request->getServer(App::HEADER_KEY_API_KEY);
        if ($ourKey['key'] !== $ourApiKey) {
            $error = 'api_error.api_key_not_found';
            $code = 401;
            $result = false;

            return;
        }

        /** @var ApiKey|null $apiKeyEntity */
        $apiKeyEntity = $app->em()->find('XF:ApiKey', $ourKey['apiKeyId']);
        if (!$apiKeyEntity) {
            $error = 'api_error.api_key_not_found';
            $code = 401;
            $result = false;

            return;
        }

        /** @var Http\Request $request */
        $request->setApiKey($apiKeyEntity->api_key);
        $request->setApiUser(0);

        $accessToken = $request->getServer(App::HEADER_KEY_ACCESS_TOKEN);

        /** @var AccessToken|null $token */
        $token = $app->finder('Truonglv\Api:AccessToken')
            ->where('token', $accessToken)
            ->whereOr([
                ['expire_date', '=', 0],
                ['expire_date', '>', \XF::$time]
            ])
            ->fetchOne();

        if ($token) {
            $request->setApiUser($token->user_id);
        }
    }

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

        $log->end_point = $request->getRequestUri();
        $log->method = strtoupper($request->getRequestMethod());

        $log->payload = [
            'GET' => $_GET,
            'POST' => $_POST
        ];

        $log->response_code = $response->httpCode();
        $log->response = $logRepo->prepareDataForLog($response->body());

        $log->save();
    }
}
