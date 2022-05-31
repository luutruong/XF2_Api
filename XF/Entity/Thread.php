<?php

namespace Truonglv\Api\XF\Entity;

class Thread extends XFCP_Thread
{
    /**
     * @param \XF\Api\Result\EntityResult $result
     * @param int $verbosity
     * @param array $options
     * @return void
     */
    protected function setupApiResultData(
        \XF\Api\Result\EntityResult $result,
        $verbosity = \XF\Entity\Thread::VERBOSITY_NORMAL,
        array $options = []
    ) {
        parent::setupApiResultData($result, $verbosity, $options);

        $result->can_watch = $this->canWatch();
        $result->view_url = $this->app()->router('public')
            ->buildLink('canonical:threads', $this);

        $visitor = \XF::visitor();
        if ($visitor->user_id > 0) {
            $result->can_report = $this->FirstPost !== null && $this->FirstPost->canReport();

            $result->can_ignore = $this->User !== null && $visitor->canIgnoreUser($this->User);
            $result->is_ignored = $visitor->isIgnoring($this->user_id);
            $result->can_upload_attachments = $this->Forum !== null && $this->Forum->canUploadAndManageAttachments();
            $result->can_stick_unstick = $this->canStickUnstick();
            $result->can_lock_unlock = $this->canLockUnlock();
        } else {
            $result->can_report = false;
            $result->can_ignore = false;
            $result->is_ignored = false;
            $result->can_upload_attachments = false;
            $result->can_stick_unstick = false;
            $result->can_lock_unlock = false;
        }

        // If specified the image will display in thread card in mobile app
        // the image MUST be viewable by guest as well
        $result->tapi_thread_image_url = null;

        if (!array_key_exists('tapi_first_post', $options)
            && $this->app()->request()->filter('with_first_post', 'bool') === true
        ) {
            $options['tapi_first_post'] = 1;
        }

        if (isset($options['tapi_last_post'])
            && $this->first_post_id !== $this->last_post_id
        ) {
            $result->includeRelation('LastPost', $verbosity, $options);
        }

        if (isset($options['tapi_first_post'])
            && $this->FirstPost !== null
        ) {
            $result->includeRelation('FirstPost', $verbosity, $options);
            if (isset($options['tapi_fetch_image'])) {
                $coverImage = $this->getCoverImage();
                if ($coverImage !== null && substr($coverImage, 0, 1) === '/') {
                    // https://xenforo.com/community/threads/200983/
                    $coverImage = \XF::canonicalizeUrl($coverImage);
                }

                $result->tapi_thread_image_url = $coverImage;
            }
        }

        if ($this->discussion_type === 'poll' && $verbosity >= self::VERBOSITY_VERBOSE) {
            $result->includeRelation('Poll', $verbosity, $options);
        }
    }
}
