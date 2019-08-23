<?php

namespace Truonglv\Api\XF\Service\Alert;

class Pusher extends XFCP_Pusher
{
    /**
     * @return string
     */
    public function tApiGetNotificationBody()
    {
        return $this->getNotificationBody();
    }
}
