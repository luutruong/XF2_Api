<?php

namespace Truonglv\Api\XF\Service\Alert;

class PusherService extends XFCP_PusherService
{
    /**
     * @return string
     */
    public function tApiGetNotificationBody()
    {
        return $this->getNotificationBody();
    }
}
