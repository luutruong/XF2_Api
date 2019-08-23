<?php

namespace Truonglv\Api\XF\Service\Conversation;

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
