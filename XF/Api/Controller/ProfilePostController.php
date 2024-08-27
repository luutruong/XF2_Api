<?php

namespace Truonglv\Api\XF\Api\Controller;

use XF;
use XF\Mvc\ParameterBag;
use Truonglv\Api\Api\ControllerPlugin\ReportPlugin;
use Truonglv\Api\Api\ControllerPlugin\ReactionPlugin;

class ProfilePostController extends XFCP_ProfilePostController
{
    public function actionPostReport(ParameterBag $params)
    {
        $profilePost = $this->assertViewableProfilePost($params->profile_post_id);
        if (XF::isApiCheckingPermissions() && !$profilePost->canReport()) {
            return $this->noPermission();
        }

        $reportPlugin = $this->plugin(ReportPlugin::class);

        return $reportPlugin->actionReport('profile_post', $profilePost);
    }

    public function actionGetTApiReactions(ParameterBag $params)
    {
        $profilePost = $this->assertViewableProfilePost($params->profile_post_id);

        $reactionPlugin = $this->plugin(ReactionPlugin::class);

        return $reactionPlugin->actionReactions('profile_post', $profilePost);
    }

    /**
     * @param \XF\Entity\ProfilePost $profilePost
     * @param mixed $page
     * @param mixed $perPage
     * @return array
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function getCommentsOnProfilePostPaginated(\XF\Entity\ProfilePost $profilePost, $page = 1, $perPage = null)
    {
        if ($perPage === null) {
            $perPage = $this->options()->tApi_recordsPerPage;
        }

        return parent::getCommentsOnProfilePostPaginated($profilePost, $page, $perPage);
    }
}
