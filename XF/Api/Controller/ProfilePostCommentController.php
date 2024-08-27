<?php

namespace Truonglv\Api\XF\Api\Controller;

use XF;
use XF\Mvc\ParameterBag;
use Truonglv\Api\Api\ControllerPlugin\ReportPlugin;
use Truonglv\Api\Api\ControllerPlugin\ReactionPlugin;

class ProfilePostCommentController extends XFCP_ProfilePostCommentController
{
    public function actionPostReport(ParameterBag $params)
    {
        $profilePostComment = $this->assertViewableProfilePostComment($params->profile_post_comment_id);
        if (XF::isApiCheckingPermissions() && !$profilePostComment->canReport($error)) {
            return $this->noPermission($error);
        }

        $reportPlugin = $this->plugin(ReportPlugin::class);

        return $reportPlugin->actionReport('profile_post_comment', $profilePostComment);
    }

    public function actionGetTApiReactions(ParameterBag $params)
    {
        $profilePostComment = $this->assertViewableProfilePostComment($params->profile_post_comment_id);

        $reactionPlugin = $this->plugin(ReactionPlugin::class);

        return $reactionPlugin->actionReactions('profile_post_comment', $profilePostComment);
    }
}
