<?php

namespace Truonglv\Api\XF\Entity;

class ConversationMaster extends XFCP_ConversationMaster
{
    protected function setupApiResultData(
        \XF\Api\Result\EntityResult $result,
        $verbosity = \XF\Entity\ConversationMaster::VERBOSITY_NORMAL,
        array $options = []
    ) {
        parent::setupApiResultData($result, $verbosity, $options);

        $visitor = \XF::visitor();
        if ($visitor->user_id > 0) {
            $result->is_unread = isset($this->Users[$visitor->user_id]) && $this->Users[$visitor->user_id]->isUnread();
        } else {
            $result->is_unread = false;
        }
    }
}
