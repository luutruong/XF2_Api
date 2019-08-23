<?php

namespace Truonglv\Api\XF\Api\Controller;

use Truonglv\Api\App;
use XF\Mvc\ParameterBag;
use Truonglv\Api\Api\ControllerPlugin\Report;

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
