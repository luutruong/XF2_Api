<?php

namespace Truonglv\Api\XF\Api\Controller;

use XF\Mvc\ParameterBag;

class Attachment extends XFCP_Attachment
{
    public function actionGetData(ParameterBag $params)
    {
        $token = $this->filter('tapi_token', 'str');
        $expectedToken = md5(
            \XF::visitor()->user_id
            . intval($params->user_id)
            . $this->app()->config('globalSalt')
        );
        if (!empty($token) && $token === $expectedToken) {
            \XF::setApiBypassPermissions(true);
        }

        return parent::actionGetData($params);
    }
}
