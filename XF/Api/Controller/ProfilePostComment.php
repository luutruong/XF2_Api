<?php

namespace Truonglv\Api\XF\Api\Controller;

use Truonglv\Api\Api\ControllerPlugin\Reaction;
use XF\Mvc\ParameterBag;
use Truonglv\Api\Api\ControllerPlugin\Report;

class ProfilePostComment extends XFCP_ProfilePostComment
{
    public function actionPostReport(ParameterBag $params)
    {
        $profilePostComment = $this->assertViewableProfilePostComment($params->profile_post_comment_id);
        if (\XF::isApiCheckingPermissions() && !$profilePostComment->canReport($error)) {
            return $this->noPermission($error);
        }

        /** @var Report $reportPlugin */
        $reportPlugin = $this->plugin('Truonglv\Api:Api:Report');

        return $reportPlugin->actionReport('profile_post_comment', $profilePostComment);
    }

    public function actionGetTApiReactions(ParameterBag $params)
    {
        $profilePostComment = $this->assertViewableProfilePostComment($params->profile_post_comment_id);

        /** @var Reaction $reactionPlugin */
        $reactionPlugin = $this->plugin('Truonglv\Api:Api:Reaction');

        return $reactionPlugin->actionReactions('profile_post_comment', $profilePostComment);
    }
}
