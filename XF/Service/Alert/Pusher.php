<?php

namespace Truonglv\Api\XF\Service\Alert;

class Pusher extends XFCP_Pusher
{
    public function tApiGetNotificationBody()
    {
        return $this->getNotificationBody();
    }
}
