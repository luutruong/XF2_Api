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
        if ($this->user_id === \XF::visitor()->user_id) {
            $result->can_upload_avatar = $this->canUploadAvatar();
        } else {
            $result->can_upload_avatar = false;
        }

        $result->ignoring = $this->Profile->ignored;
        $result->following = $this->Profile->following;
        // if this key is TRUE in the user profile will have a tick icon
        // make this option config from server to support third party add-on
        $result->tapi_is_verified = $this->is_staff;
    }
}
