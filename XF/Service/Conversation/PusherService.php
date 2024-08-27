<?php

namespace Truonglv\Api\XF\Service\Conversation;

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
