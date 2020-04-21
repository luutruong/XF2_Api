<?php

namespace Truonglv\Api\XF\Api\Controller;

use XF\Mvc\ParameterBag;
use Truonglv\Api\Api\ControllerPlugin\Report;
use Truonglv\Api\Api\ControllerPlugin\Reaction;

class Post extends XFCP_Post
{
    public function actionPostReport(ParameterBag $params)
    {
        $post = $this->assertViewablePost($params->post_id);
        if (\XF::isApiCheckingPermissions() && !$post->canReport($error)) {
            return $this->noPermission($error);
        }

        /** @var Report $reportPlugin */
        $reportPlugin = $this->plugin('Truonglv\Api:Api:Report');

        return $reportPlugin->actionReport('post', $post);
    }

    public function actionGetTApiReactions(ParameterBag $params)
    {
        $post = $this->assertViewablePost($params->post_id);

        /** @var Reaction $reactionPlugin */
        $reactionPlugin = $this->plugin('Truonglv\Api:Api:Reaction');

        return $reactionPlugin->actionReactions('post', $post);
    }
}
