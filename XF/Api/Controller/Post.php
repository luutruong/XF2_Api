<?php

namespace Truonglv\Api\XF\Api\Controller;

use Truonglv\Api\Api\ControllerPlugin\Report;
use XF\Mvc\ParameterBag;

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
}
