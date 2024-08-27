<?php

namespace Truonglv\Api\XF\Api\Controller;

use XF;
use XF\Mvc\ParameterBag;
use Truonglv\Api\Api\ControllerPlugin\ReportPlugin;
use Truonglv\Api\Api\ControllerPlugin\ReactionPlugin;

class PostController extends XFCP_PostController
{
    public function actionPostReport(ParameterBag $params)
    {
        $post = $this->assertViewablePost($params->post_id);
        if (XF::isApiCheckingPermissions() && !$post->canReport($error)) {
            return $this->noPermission($error);
        }

        $reportPlugin = $this->plugin(ReportPlugin::class);

        return $reportPlugin->actionReport('post', $post);
    }

    public function actionGetTApiReactions(ParameterBag $params)
    {
        $post = $this->assertViewablePost($params->post_id);

        $reactionPlugin = $this->plugin(ReactionPlugin::class);

        return $reactionPlugin->actionReactions('post', $post);
    }
}
