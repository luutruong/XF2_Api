<?php

namespace Truonglv\Api\XF\Pub\Controller;

use Truonglv\Api\App;
use Truonglv\Api\Util\PasswordDecrypter;
use XF\ControllerPlugin\Login;
use XF\Entity\User;

class Misc extends XFCP_Misc
{
    public function actionTApiGoto()
    {
        if (!App::isRequestFromApp()) {
            return $this->redirect($this->buildLink('index'));
        }

        $payload = $this->filter('d', 'str');
        $sign = $this->filter('s', 'str');
        if ($sign === '') {
            return $this->redirect($this->buildLink('index'));
        }

        /** @var array|null $data */
        $data = null;

        try {
            $data = PasswordDecrypter::decrypt($payload, $this->app()->options()->tApi_encryptKey);
        } catch (\InvalidArgumentException $e) {
        }

        if ($data === null) {
            return $this->redirect($this->buildLink('index'));
        }

        $data = \json_decode($data, true);
        if (!\is_array($data)) {
            return $this->redirect($this->buildLink('index'));
        }

        $computeSign = \md5(\strval(\json_encode($data)));
        if (!\hash_equals($sign, $computeSign)) {
            return $this->redirect($this->buildLink('index'));
        }

        $userId = $data['user_id'];
        $targetUrl = $data['url'];

        $linkInfo = $this->app->stringFormatter()->getLinkClassTarget($targetUrl);
        if ($linkInfo['trusted'] === false) {
            return $this->redirectPermanently($targetUrl);
        }

        /** @var User|null $user */
        $user = $this->app->em()->find('XF:User', $userId);
        if ($user !== null) {
            /** @var Login $loginPlugin */
            $loginPlugin = $this->plugin('XF:Login');
            $loginPlugin->completeLogin($user, false);
        }

        return $this->redirectPermanently($targetUrl);
    }
}
