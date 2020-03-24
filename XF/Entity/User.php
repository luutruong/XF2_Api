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

        if ($verbosity >= self::VERBOSITY_VERBOSE) {
            $result->tapi_about_data = $this->getTApiAboutTabData();
        }
    }

    /**
     * @return array
     */
    protected function getTApiAboutTabData()
    {
        $data = [];

        if ($this->custom_title !== '') {
            $data[] = [
                'label' => \XF::phrase('custom_title'),
                'value' => $this->custom_title,
            ];
        }

        $birthday = $this->Profile->getBirthday();
        if (\count($birthday) > 0) {
            $language = $this->app()->language(\XF::visitor()->language_id);

            $data[] = [
                'label' => \XF::phrase('date_of_birth'),
                'value' => $language->date($birthday['timeStamp'], $birthday['format']),
            ];
        }

        if ($this->Profile->website !== '') {
            $data[] = [
                'label' => \XF::phrase('website'),
                'value' => $this->Profile->website,
            ];
        }

        if ($this->Profile->location !== '') {
            $data[] = [
                'label' => \XF::phrase('location'),
                'value' => $this->Profile->location,
            ];
        }

        $showingCustomFieldIds = ['skype', 'facebook', 'twitter'];
        $customFields = $this->Profile->custom_fields;

        foreach ($showingCustomFieldIds as $customFieldId) {
            $value = $customFields->getFieldValue($customFieldId);
            if ($value === null) {
                continue;
            }

            $definition = $customFields->getDefinition($customFieldId);
            $data[] = [
                'label' => $definition->title,
                'value' => $value,
            ];
        }

        return $data;
    }
}
