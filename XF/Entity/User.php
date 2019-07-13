<?php

namespace Truonglv\Api\XF\Entity;

class User extends XFCP_User
{
    protected function setupApiResultData(
        \XF\Api\Result\EntityResult $result,
        $verbosity = \XF\Entity\User::VERBOSITY_NORMAL,
        array $options = []
    ) {
        parent::setupApiResultData($result, $verbosity, $options);

        if (!isset($result->can_start_converse)) {
            $result->can_start_converse = $this->canStartConversation();
        }
    }
}
