<?php

namespace Truonglv\Api\XF\Entity;

class User extends XFCP_User
{
    /**
     * @var bool
     */
    private $tapiAllowSelfDelete = false;

    public function setTapiAllowSelfDelete(bool $tapiAllowSelfDelete): void
    {
        $this->tapiAllowSelfDelete = $tapiAllowSelfDelete;
    }

    /**
     * @param mixed $error
     * @return bool
     */
    public function canTapiDelete(&$error = null): bool
    {
        if ($this->is_super_admin || $this->is_admin || $this->is_moderator) {
            return false;
        }

        return $this->app()->options()->tApi_allowSelfDelete > 0;
    }

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
        $result->can_be_delete = $this->canTapiDelete();

        if ($verbosity >= self::VERBOSITY_VERBOSE) {
            $result->tapi_about_data = $this->getTApiAboutTabData();
        }

        $result->tapi_enable_ads = !$this->hasPermission('general', 'tapi_disableAdsInApp');

        if (isset($options['tapi_permissions'], $options['tapi_permissions']['username'])) {
            $result->can_change_username = $this->canChangeUsername();
        }
    }

    /**
     * @param mixed $message
     * @param mixed $key
     * @param mixed $specificError
     * @return void
     */
    public function error($message, $key = null, $specificError = true)
    {
        if ($message instanceof \XF\Phrase
            && $this->tapiAllowSelfDelete
            && $message->getName() === 'you_cannot_delete_your_own_account'
        ) {
            return;
        }

        parent::error($message, $key, $specificError);
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
