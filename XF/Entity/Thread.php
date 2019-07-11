<?php

namespace Truonglv\Api\XF\Entity;

class Thread extends XFCP_Thread
{
    protected function setupApiResultData(
        \XF\Api\Result\EntityResult $result,
        $verbosity = \XF\Entity\Thread::VERBOSITY_NORMAL,
        array $options = []
    ) {
        parent::setupApiResultData($result, $verbosity, $options);

        if (!isset($result->can_watch)) {
            $result->can_watch = $this->canWatch();
        }
    }
}
