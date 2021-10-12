<?php

namespace Truonglv\Api;

use XF\Container;
use XF\Entity\ApiKey;
use XF\Http\ResponseFile;
use XF\Http\ResponseStream;
use Truonglv\Api\Entity\Log;
use Truonglv\Api\Http\Request;
use Truonglv\Api\Entity\AccessToken;
use XF\Api\Controller\AbstractController;

class Listener
{
    /**
     * @param \XF\Api\App $app
     * @return void
     */
    public static function appApiSetup(\XF\Api\App $app)
    {
        $app->container()->set('request', function (Container $c) {
            $request = new Http\Request($c['inputFilterer']);
            $request->setCookiePrefix($c['config']['cookie']['prefix']);

            return $request;
        });
    }

    /**
     * @param \XF\Http\Request $request
     * @param mixed $result
     * @param mixed $error
     * @param mixed $code
     * @return void
     */
    public static function appApiValidateRequest(\XF\Http\Request $request, &$result, &$error, &$code)
    {
        $requestApiKey = $request->getServer('HTTP_XF_TAPI_KEY');
        if (strlen($requestApiKey) === 0) {
            return;
        }

        $app = \XF::app();
        $ourKey = $app->options()->tApi_apiKey;

        /** @var ApiKey|null $apiKeyEntity */
        $apiKeyEntity = $app->em()->find('XF:ApiKey', $ourKey['apiKeyId']);
        if ($apiKeyEntity === null) {
            $error = 'api_error.api_key_not_found';
            $code = 401;
            $result = false;

            return;
        }

        /** @var Request $ourRequest */
        $ourRequest = $request;
        $ourRequest->setApiKey($apiKeyEntity->api_key);
        $ourRequest->setApiUser(0);

        $accessToken = $request->getServer(App::HEADER_KEY_ACCESS_TOKEN);
        if ($accessToken !== false) {
            /** @var AccessToken|null $token */
            $token = $app->finder('Truonglv\Api:AccessToken')
                ->where('token', $accessToken)
                ->whereOr([
                    ['expire_date', '=', 0],
                    ['expire_date', '>', \XF::$time]
                ])
                ->fetchOne();

            if ($token !== null) {
                $ourRequest->setApiUser($token->user_id);
            }
        }
    }


    /**
     * @param \XF\Api\App $app
     * @param \XF\Http\Response $response
     * @throws \XF\PrintableException
     * @return void
     */
    public static function onAppApiComplete(\XF\Api\App $app, \XF\Http\Response &$response)
    {
        $request = $app->request();
        /** @var \Truonglv\Api\Repository\Log $logRepo */
        $logRepo = $app->repository('Truonglv\Api:Log');
        $logRepo->log($request, $response);
    }
}
