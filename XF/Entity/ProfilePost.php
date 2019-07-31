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
        
        $result->can_comment = $this->canComment();
    }
}
