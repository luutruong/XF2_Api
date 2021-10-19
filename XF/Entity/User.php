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

        $result->can_view_current_activity = $this->canViewCurrentActivity();
        $result->can_upload_attachment_on_profile = $this->canUploadAndManageAttachmentsOnProfile();

        $result->ignoring = $this->Profile !== null ? $this->Profile->ignored : [];
        $result->following = $this->Profile !== null ? $this->Profile->following : [];

        if ($verbosity >= self::VERBOSITY_VERBOSE) {
            $result->tapi_about_data = $this->getTApiAboutTabData();
        }
    }

    /**
     * @return array
     */
    protected function getTApiAboutTabData()
    {
        $language = $this->app()->language(\XF::visitor()->language_id);

        $data = [
            [
                'label' => \XF::phrase('joined'),
                'value' => $language->date($this->register_date, 'absolute'),
            ],
            [
                'label' => \XF::phrase('messages'),
                'value' => $language->numberFormat($this->message_count)
            ],
            [
                'label' => \XF::phrase('reaction_score'),
                'value' => $language->numberFormat($this->reaction_score)
            ],
        ];

        if ($this->app()->options()->enableTrophies) {
            $data[] = [
                'label' => \XF::phrase('trophy_points'),
                'value' => $language->numberFormat($this->trophy_points)
            ];
        }

        if ($this->custom_title !== '') {
            $data[] = [
                'label' => \XF::phrase('custom_title'),
                'value' => $this->custom_title,
            ];
        }

        $birthday = $this->Profile !== null ? $this->Profile->getBirthday() : [];
        if (\count($birthday) > 0) {
            $data[] = [
                'label' => \XF::phrase('date_of_birth'),
                'value' => $language->date($birthday['timeStamp'], $birthday['format']),
            ];
        }

        if ($this->Profile !== null && $this->Profile->website !== '') {
            $data[] = [
                'label' => \XF::phrase('website'),
                'value' => $this->Profile->website,
            ];
        }

        if ($this->Profile !== null && $this->Profile->location !== '') {
            $data[] = [
                'label' => \XF::phrase('location'),
                'value' => $this->Profile->location,
            ];
        }

        if ($this->Profile !== null) {
            $showingCustomFieldIds = ['skype', 'facebook', 'twitter'];
            $customFields = $this->Profile->custom_fields;

            foreach ($showingCustomFieldIds as $customFieldId) {
                $value = $customFields->getFieldValue($customFieldId);
                if ($value === null) {
                    continue;
                }

                /** @var mixed $definition */
                $definition = $customFields->getDefinition($customFieldId);
                $data[] = [
                    'label' => $definition->title,
                    'value' => $value,
                ];
            }
        }

        return $data;
    }
}
