<?php

namespace Truonglv\Api\XF\Entity;

use Truonglv\Api\App;

class ProfilePost extends XFCP_ProfilePost
{
    protected function setupApiResultData(
        \XF\Api\Result\EntityResult $result,
        $verbosity = \XF\Entity\ProfilePost::VERBOSITY_NORMAL,
        array $options = []
    ) {
        parent::setupApiResultData($result, $verbosity, $options);

        App::includeMessageHtmlIfNeeded($result, $this);

        $visitor = \XF::visitor();
        if ($visitor->user_id > 0) {
            $result->can_comment = $this->canComment();
            $result->can_report = $this->canReport();

            $result->can_ignore = $this->User && $visitor->canIgnoreUser($this->User);
            $result->is_ignored = $visitor->isIgnoring($this->user_id);
        } else {
            $result->can_comment = false;
            $result->can_report = false;
            $result->can_ignore = false;
            $result->is_ignored = false;
        }
    }
}
