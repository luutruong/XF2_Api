<?php

namespace Truonglv\Api\XF\Api\Controller;

use XF;
use XF\Mvc\ParameterBag;
use Truonglv\Api\Api\ControllerPlugin\Report;
use Truonglv\Api\Api\ControllerPlugin\Reaction;

class ProfilePostController extends XFCP_ProfilePostController
{
    public function actionPostReport(ParameterBag $params)
    {
        $profilePost = $this->assertViewableProfilePost($params->profile_post_id);
        if (XF::isApiCheckingPermissions() && !$profilePost->canReport()) {
            return $this->noPermission();
        }

        /** @var Report $reportPlugin */
        $reportPlugin = $this->plugin('Truonglv\Api:Api:Report');

        return $reportPlugin->actionReport('profile_post', $profilePost);
    }

    public function actionGetTApiReactions(ParameterBag $params)
    {
        $profilePost = $this->assertViewableProfilePost($params->profile_post_id);

        /** @var Reaction $reactionPlugin */
        $reactionPlugin = $this->plugin('Truonglv\Api:Api:Reaction');

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
