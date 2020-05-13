<?php

namespace Truonglv\Api\XF\Pub\Controller;

use XF\Entity\User;
use Truonglv\Api\App;
use XF\ControllerPlugin\Login;
use Truonglv\Api\Util\PasswordDecrypter;

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

        /** @var User|null $user */
        $user = $this->app->em()->find('XF:User', $userId);
        $isActive = ($data['date'] + 3600) > \XF::$time;
        if ($user !== null && $isActive) {
            /** @var Login $loginPlugin */
            $loginPlugin = $this->plugin('XF:Login');
            $loginPlugin->completeLogin($user, false);
        }

        return $this->redirectPermanently($targetUrl);
    }
}
