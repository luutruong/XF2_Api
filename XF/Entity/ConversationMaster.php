<?php

namespace Truonglv\Api\XF\Entity;

use Truonglv\Api\App;

class ConversationMaster extends XFCP_ConversationMaster
{
    /**
     * @param \XF\Api\Result\EntityResult $result
     * @param int $verbosity
     * @param array $options
     * @return void
     */
    protected function setupApiResultData(
        \XF\Api\Result\EntityResult $result,
        $verbosity = \XF\Entity\ConversationMaster::VERBOSITY_NORMAL,
        array $options = []
    ) {
        parent::setupApiResultData($result, $verbosity, $options);

        $visitor = \XF::visitor();
        if ($visitor->user_id > 0) {
            $result->is_unread = isset($this->Users[$visitor->user_id])
                ? $this->Users[$visitor->user_id]->isUnread()
                : false;
        } else {
            $result->is_unread = false;
        }

        $hasIncludeLastMessage = App::getRequest()->filter('tapi_last_message', 'bool');
        if ($hasIncludeLastMessage === true) {
            $result->includeRelation('LastMessage');
        }
    }
}
