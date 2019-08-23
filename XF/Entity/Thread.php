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

        if (!isset($result->can_watch)) {
            $result->can_watch = $this->canWatch();
        }

        $visitor = \XF::visitor();
        if ($visitor->user_id > 0) {
            $result->can_report = $this->FirstPost->canReport();

            $result->can_ignore = $this->User && $visitor->canIgnoreUser($this->User);
            $result->is_ignored = $visitor->isIgnoring($this->user_id);
        } else {
            $result->can_report = false;
            $result->can_ignore = false;
            $result->is_ignored = false;
        }
    }
}
