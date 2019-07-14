<?php

namespace Truonglv\Api\XF\Api\Controller;

use XF\Mvc\ParameterBag;
use XF\Service\User\Follow;

class User extends XFCP_User
{
    public function actionPostFollowing(ParameterBag $params)
    {
        $user = $this->assertViewableUser($params->user_id);
        $visitor = \XF::visitor();
        if (\XF::isApiCheckingPermissions() && !$visitor->canFollowUser($user)) {
            return $this->noPermission();
        }

        /** @var Follow $follow */
        $follow = $this->service('XF:User\Follow', $user);
        $wasFollowing = $visitor->isFollowing($user);

        if ($wasFollowing) {
            $follow->unfollow();
        } else {
            $follow->follow();
        }

        return $this->apiSuccess([
            'action' => $wasFollowing ? 'unfollow' : 'follow'
        ]);
    }
}
