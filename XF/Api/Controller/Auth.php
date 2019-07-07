<?php

namespace Truonglv\Api\XF\Api\Controller;

use Truonglv\Api\Util\PasswordDecrypter;

class Auth extends XFCP_Auth
{
    public function actionPostAppLogin()
    {
        $password = $this->filter('password', 'str');
        $decrypted = '';

        try {
            $decrypted = PasswordDecrypter::decrypt($password);
        } catch (\InvalidArgumentException $e) {
        }

        $this->request()->set('password', $decrypted);

        return $this->rerouteController(__CLASS__, 'post');
    }
}
