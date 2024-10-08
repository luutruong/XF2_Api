<?php

namespace Truonglv\Api\XF\Api\Mvc\Renderer;

use XF;
use Truonglv\Api\App;

class Api extends XFCP_Api
{
    /**
     * @return array
     */
    protected function getVisitorResponseExtras()
    {
        $extra = parent::getVisitorResponseExtras();
        $visitor = XF::visitor();

        $alertRepo = XF::app()->repository(XF\Repository\UserAlertRepository::class);
        $finder = $alertRepo->findAlertsForUser($visitor->user_id);
        $finder->where('view_date', 0);
        $finder->where('content_type', App::getSupportAlertContentTypes());

        $extra['alerts_unread'] = $finder->total();
        $extra['_alerts_unread'] = $visitor->alerts_unread;

        return $extra;
    }
}
