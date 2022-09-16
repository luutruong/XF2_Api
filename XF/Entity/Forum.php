<?php

namespace Truonglv\Api\XF\Entity;

use XF;

class Forum extends XFCP_Forum
{
    /**
     * @param mixed $verbosity
     * @param array $options
     * @return \XF\Api\Result\EntityResult
     */
    public function getNodeTypeApiData($verbosity = \XF\Entity\Forum::VERBOSITY_NORMAL, array $options = [])
    {
        $result = parent::getNodeTypeApiData($verbosity, $options);

        $result->can_watch = $this->canWatch();
        $result->is_watched = false;

        $visitor = XF::visitor();
        if ($visitor->user_id > 0) {
            $result->is_watched = isset($this->Watch[$visitor->user_id]);
        }

        return $result;
    }
}
