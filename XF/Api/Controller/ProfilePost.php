<?php

namespace Truonglv\Api\XF\Api\Controller;

use Truonglv\Api\App;
use XF\Mvc\ParameterBag;
use Truonglv\Api\Api\ControllerPlugin\Report;
use Truonglv\Api\Api\ControllerPlugin\Reaction;

class ProfilePost extends XFCP_ProfilePost
{
    public function actionPostReport(ParameterBag $params)
    {
        $profilePost = $this->assertViewableProfilePost($params->profile_post_id);
        if (\XF::isApiCheckingPermissions() && !$profilePost->canReport()) {
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
        if (App::isRequestFromApp()) {
            $perPage = $this->options()->tApi_recordsPerPage;
        }

        return parent::getCommentsOnProfilePostPaginated($profilePost, $page, $perPage);
    }

    /**
     * @param \XF\Entity\ProfilePost $profilePost
     * @return \XF\Finder\ProfilePostComment
     */
    protected function setupCommentsFinder(\XF\Entity\ProfilePost $profilePost)
    {
        $finder = parent::setupCommentsFinder($profilePost);
        if (App::isRequestFromApp()) {
            $finder->resetWhere();

            $finder->where('profile_post_id', $profilePost->profile_post_id);
            $finder->whereOr([
                ['message_state', '=', 'visible'],
                [
                    'message_state' => 'moderated',
                    'user_id' => \XF::visitor()->user_id
                ]
            ]);
        }

        return $finder;
    }
}
