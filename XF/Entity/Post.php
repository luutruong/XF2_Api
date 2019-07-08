<?php

namespace Truonglv\Api\XF\Entity;

use Truonglv\Api\App;

class Post extends XFCP_Post
{
    protected function setupApiResultData(
        \XF\Api\Result\EntityResult $result,
        $verbosity = \XF\Entity\Post::VERBOSITY_NORMAL,
        array $options = []
    ) {
        parent::setupApiResultData($result, $verbosity, $options);

        App::includeMessageHtmlIfNeeded($result, $this);
    }
}
