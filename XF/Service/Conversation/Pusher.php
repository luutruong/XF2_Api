<?php

namespace Truonglv\Api\XF\Service\Conversation;

class Pusher extends XFCP_Pusher
{
    public function tApiGetNotificationBody()
    {
        return $this->getNotificationBody();
    }
}
