<?php

namespace Truonglv\Api\XF\Api\Mvc\Renderer;

use Truonglv\Api\App;
use XF\Repository\UserAlert;

class Api extends XFCP_Api
{
    protected function getVisitorResponseExtras()
    {
        $extra = parent::getVisitorResponseExtras();

        $request = \XF::app()->request();
        if (!empty($request->getServer(App::HEADER_KEY_APP_VERSION))) {
            $visitor = \XF::visitor();

            /** @var UserAlert $alertRepo */
            $alertRepo = \XF::app()->repository('XF:UserAlert');
            $finder = $alertRepo->findAlertsForUser($visitor->user_id);
            $finder->where('view_date', 0);
            $finder->where('content_type', App::getSupportAlertContentTypes());

            $extra['alerts_unread'] = $finder->total();
            $extra['_alerts_unread'] = $visitor->alerts_unread;
        }

        return $extra;
    }
}
