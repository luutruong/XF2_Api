<?php

namespace Truonglv\Api\XF\Api\Controller;

use Truonglv\Api\App;
use XF\Mvc\ParameterBag;

class ProfilePostComments extends XFCP_ProfilePostComments
{
    public function actionPost(ParameterBag $params)
    {
        $this->request()->set(App::PARAM_KEY_INCLUDE_MESSAGE_HTML, 1);

        return parent::actionPost($params);
    }
}
