<?php

namespace Truonglv\Api\XF\Entity;

class Node extends XFCP_Node
{
    /**
     * @param \XF\Api\Result\EntityResult $result
     * @param mixed $verbosity
     * @param array $options
     * @return void
     */
    protected function setupApiResultData(
        \XF\Api\Result\EntityResult $result,
        $verbosity = \XF\Entity\Node::VERBOSITY_NORMAL,
        array $options = []
    ) {
        parent::setupApiResultData($result, $verbosity, $options);

        $result->tapi_description_plain_text = trim(strip_tags($this->description));
    }
}
