<?php

namespace Truonglv\Api\XF\Entity;

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

        return $result;
    }
}
