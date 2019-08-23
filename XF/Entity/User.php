<?php

namespace Truonglv\Api\XF\Entity;

class User extends XFCP_User
{
    /**
     * @param \XF\Api\Result\EntityResult $result
     * @param int $verbosity
     * @param array $options
     * @return void
     */
    protected function setupApiResultData(
        \XF\Api\Result\EntityResult $result,
        $verbosity = \XF\Entity\User::VERBOSITY_NORMAL,
        array $options = []
    ) {
        parent::setupApiResultData($result, $verbosity, $options);

        $result->can_start_converse = $this->canStartConversation();
        $result->can_be_reported = $this->canBeReported();

        $result->ignoring = $this->Profile->ignored;
        $result->following = $this->Profile->following;
    }
}
