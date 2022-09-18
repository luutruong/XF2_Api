<?php

namespace Truonglv\Api\XFRM\Entity;

class ResourceItem extends XFCP_ResourceItem
{
    protected function setupApiResultData(
        \XF\Api\Result\EntityResult $result,
        $verbosity = \XFRM\Entity\ResourceItem::VERBOSITY_NORMAL,
        array $options = []
    ) {
        parent::setupApiResultData($result, $verbosity, $options);

        $result->can_watch = $this->canWatch();
    }
}
