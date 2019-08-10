<?php

namespace Truonglv\Api\XF\Api\Controller;

use Truonglv\Api\Api\ControllerPlugin\Report;
use Truonglv\Api\App;
use XF\Mvc\ParameterBag;
use XF\Mvc\Entity\Entity;
use XF\Service\User\Follow;
use XF\Repository\UserFollow;

class User extends XFCP_User
{
    public function actionPostFollowing(ParameterBag $params)
    {
        $user = $this->assertViewableUser($params->user_id);
        $visitor = \XF::visitor();
        if (\XF::isApiCheckingPermissions() && !$visitor->canFollowUser($user)) {
            return $this->noPermission();
        }

        if (!$visitor->isFollowing($user)) {
            /** @var Follow $follow */
            $follow = $this->service('XF:User\Follow', $user);
            $follow->follow();
        }

        return $this->apiSuccess();
    }

    public function actionGetFollowing(ParameterBag $params)
    {
        $user = $this->assertViewableUser($params->user_id);

        $page = $this->filterPage();
        $perPage = App::$followingPerPage;

        /** @var UserFollow $followRepo */
        $followRepo = $this->repository('XF:UserFollow');
        $finder = $followRepo->findFollowingForProfile($user);

        $total = $finder->total();
        $entities = $total > 0
            ? $finder->limitByPage($page, $perPage)->fetch()
            : [];
        $users = [];

        /** @var \XF\Entity\UserFollow $entity */
        foreach ($entities as $entity) {
            $users[$entity->user_id] = $entity->FollowUser;
        }

        return $this->apiResult([
            'users' => $this->em()->getBasicCollection($users)->toApiResults(Entity::VERBOSITY_VERBOSE),
            'pagination' => $this->getPaginationData($entities, $page, $perPage, $total)
        ]);
    }

    public function actionDeleteFollowing(ParameterBag $params)
    {
        $user = $this->assertViewableUser($params->user_id);
        $visitor = \XF::visitor();
        if (\XF::isApiCheckingPermissions() && !$visitor->canFollowUser($user)) {
            return $this->noPermission();
        }

        if ($visitor->isFollowing($user)) {
            /** @var Follow $follow */
            $follow = $this->service('XF:User\Follow', $user);
            $follow->unfollow();
        }

        return $this->apiSuccess();
    }

    public function actionPostReport(ParameterBag $params)
    {
        $user = $this->assertViewableUser($params->user_id);
        if (\XF::isApiCheckingPermissions() && !$user->canBeReported()) {
            return $this->noPermission();
        }

        /** @var Report $reportPlugin */
        $reportPlugin = $this->plugin('Truonglv\Api:Api:Report');
        return $reportPlugin->actionReport('user', $user);
    }

    protected function setupProfilePostFinder(\XF\Entity\User $user)
    {
        $finder = parent::setupProfilePostFinder($user);
        if (App::isRequestFromApp()) {
            $finder->resetWhere();

            $finder->where('profile_user_id', $user->user_id);
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
