<?php

namespace Truonglv\Api\XF\Entity;

use Truonglv\Api\App;

class ProfilePostComment extends XFCP_ProfilePostComment
{
    protected function setupApiResultData(
        \XF\Api\Result\EntityResult $result,
        $verbosity = \XF\Entity\ProfilePostComment::VERBOSITY_NORMAL,
        array $options = []
    ) {
        parent::setupApiResultData($result, $verbosity, $options);

        App::includeMessageHtmlIfNeeded($result, $this);
    }
}
