<?php

namespace Truonglv\Api\XFRM\Api\Controller;

use XF\Mvc\ParameterBag;
use XF\Api\ControllerPlugin\ReactionPlugin;
use Truonglv\Api\Api\ControllerPlugin\ReactionPlugin;

class ResourceUpdate extends XFCP_ResourceUpdate
{
    public function actionPostReact(ParameterBag $params)
    {
        $update = $this->assertViewableUpdate($params['resource_update_id']);

        $reactPlugin = $this->plugin(ReactionPlugin::class);

        return $reactPlugin->actionReact($update);
    }

    public function actionGetTApiReactions(ParameterBag $params)
    {
        $update = $this->assertViewableUpdate($params['resource_update_id']);

        $reactionPlugin = $this->plugin(ReactionPlugin::class);

        return $reactionPlugin->actionReactions('resource_update', $update);
    }
}
