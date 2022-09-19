<?php

namespace Truonglv\Api\XFRM\Api\Controller;

use Truonglv\Api\Api\ControllerPlugin\Reaction;
use XF\Mvc\ParameterBag;

class ResourceUpdate extends XFCP_ResourceUpdate
{
    public function actionPostReact(ParameterBag $params)
    {
        $update = $this->assertViewableUpdate($params['resource_update_id']);

        /** @var \XF\Api\ControllerPlugin\Reaction $reactPlugin */
        $reactPlugin = $this->plugin('XF:Api:Reaction');
        return $reactPlugin->actionReact($update);
    }

    public function actionGetTApiReactions(ParameterBag $params)
    {
        $update = $this->assertViewableUpdate($params['resource_update_id']);

        /** @var Reaction $reactionPlugin */
        $reactionPlugin = $this->plugin('Truonglv\Api:Api:Reaction');

        return $reactionPlugin->actionReactions('resource_update', $update);
    }
}
