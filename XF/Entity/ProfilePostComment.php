<?php

namespace Truonglv\Api\XF\Entity;

use XF;
use Truonglv\Api\App;

class ProfilePostComment extends XFCP_ProfilePostComment
{
    /**
     * @param \XF\Api\Result\EntityResult $result
     * @param int $verbosity
     * @param array $options
     * @return void
     */
    protected function setupApiResultData(
        \XF\Api\Result\EntityResult $result,
        $verbosity = \XF\Entity\ProfilePostComment::VERBOSITY_NORMAL,
        array $options = []
    ) {
        parent::setupApiResultData($result, $verbosity, $options);

        $visitor = XF::visitor();
        if ($visitor->user_id > 0) {
            $result->can_report = $this->canReport();
            $result->can_ignore = $this->User !== null && $visitor->canIgnoreUser($this->User);
            $result->is_ignored = $visitor->isIgnoring($this->user_id);
        } else {
            $result->can_report = false;
            $result->can_ignore = false;
            $result->is_ignored = false;
        }

        App::setupApiResultReactions($result, $this);

        $stringFormatter = $this->app()->stringFormatter();
        $plainText = $stringFormatter->stripBbCode($this->message, [
            'stripQuote' => true
        ]);

        $result->tapi_message_plain_text = $plainText;
        $result->tapi_message_plain_text_preview = $stringFormatter->wholeWordTrim(
            $plainText,
            $this->app()->options()->tApi_discussionPreviewLength
        );
    }
}
