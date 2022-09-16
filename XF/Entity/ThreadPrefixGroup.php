<?php

namespace Truonglv\Api\XF\Entity;

use LogicException;

class ThreadPrefixGroup extends XFCP_ThreadPrefixGroup
{
    protected function setupApiResultData(
        \XF\Api\Result\EntityResult $result,
        $verbosity = self::VERBOSITY_NORMAL,
        array $options = []
    ) {
        try {
            parent::setupApiResultData($result, $verbosity, $options);
        } catch (LogicException $e) {
        }

        $result->prefix_group_id = $this->prefix_group_id;
        $result->display_order = $this->display_order;
        $result->title = $this->title;
    }
}
