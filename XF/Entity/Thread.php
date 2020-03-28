<?php

namespace Truonglv\Api\XF\Entity;

use Truonglv\Api\App;
use Truonglv\Api\Util\BinarySearch;

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
            $result->can_report = $this->FirstPost->canReport();

            $result->can_ignore = $this->User && $visitor->canIgnoreUser($this->User);
            $result->is_ignored = $visitor->isIgnoring($this->user_id);
            $result->can_upload_attachments = $this->Forum->canUploadAndManageAttachments();
        } else {
            $result->can_report = false;
            $result->can_ignore = false;
            $result->is_ignored = false;
            $result->can_upload_attachments = false;
        }

        // If specified the image will display in thread card in mobile app
        // the image MUST be viewable by guest as well
        $result->tapi_thread_image_url = null;

        if (isset($options['tapi_first_post'])) {
            $result->includeRelation('FirstPost', $verbosity, $options);
            if (isset($options['tapi_fetch_image'])) {
                // Base image height (pixels) which support in mobile app
                $baseRatio = 0.8;

                $imageRatios = [];
                $ratioIndexMap = [];
                $ratioIndex = 0;

                foreach ($this->FirstPost->Attachments as $attachment) {
                    if ($attachment->Data->width === 0 || $attachment->Data->height === 0) {
                        continue;
                    }

                    $ratio = \min(
                        $attachment->Data->width / $attachment->Data->height,
                        $attachment->Data->height / $attachment->Data->width
                    );
                    $imageRatios[$ratioIndex] = $ratio;
                    $ratioIndexMap[$ratioIndex] = $attachment;

                    $ratioIndex++;
                }

                if (\count($imageRatios) === 0) {
                    return;
                }

                \sort($imageRatios, SORT_ASC);

                $best = BinarySearch::findClosestNumber($imageRatios, $baseRatio);
                foreach ($imageRatios as $index => $ratio) {
                    if ($best === $ratio) {
                        $result->tapi_thread_image_url = App::buildAttachmentLink($ratioIndexMap[$index]);

                        break;
                    }
                }
            }
        }

        if ($this->discussion_type === 'poll' && $verbosity >= self::VERBOSITY_VERBOSE) {
            $result->includeRelation('Poll', $verbosity, $options);
        }
    }
}
