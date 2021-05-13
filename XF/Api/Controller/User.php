<?php

namespace Truonglv\Api\XF\Api\Controller;

use Truonglv\Api\App;
use XF\Api\Result\EntityResults;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\ParameterBag;
use XF\Mvc\Entity\Entity;
use XF\Service\User\Follow;
use XF\Repository\UserFollow;
use Truonglv\Api\Api\ControllerPlugin\Report;

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
        $perPage = $this->app()->options()->tApi_recordsPerPage;

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

    public function actionGetThreads(ParameterBag $params)
    {
        $user = $this->assertViewableUser($params->user_id);

        /** @var \XF\Repository\Thread $threadRepo */
        $threadRepo = $this->repository('XF:Thread');

        $threadFinder = $threadRepo->findThreadsStartedByUser($user->user_id);
        $this->setupTApiThreadFinder($threadFinder, $user);

        $page = $this->filterPage();
        $perPage = $this->options()->discussionsPerPage;

        $total = $threadFinder->total();
        $this->assertValidApiPage($page, $perPage, $total);

        $threads = $threadFinder->limitByPage($page, $perPage)->fetch();

        $data = [
            'pagination' => $this->getPaginationData($threads, $page, $perPage, $total),
            'threads' => $this->prepareTApiThreadsToResults($threads),
        ];

        return $this->apiResult($data);
    }

    protected function prepareTApiThreadsToResults(AbstractCollection $threads): EntityResults
    {
        return $threads->toApiResults(Entity::VERBOSITY_VERBOSE);
    }

    protected function setupTApiThreadFinder(\XF\Finder\Thread $finder, \XF\Entity\User $user): void
    {
        $finder->with('api');

        if (\XF::isApiCheckingPermissions()) {
            /** @var \XF\Repository\Forum $forumRepo */
            $forumRepo = $this->repository('XF:Forum');
            $forums = $forumRepo->getViewableForums();

            $finder->where('node_id', $forums->keys())
                ->where('discussion_state', 'visible');
        }
    }

    /**
     * @param \XF\Entity\User $user
     * @param mixed $page
     * @param mixed $perPage
     * @return array
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function getProfilePostsForUserPaginated(\XF\Entity\User $user, $page = 1, $perPage = null)
    {
        if (App::isRequestFromApp()) {
            $perPage = $this->options()->tApi_recordsPerPage;
        }

        return parent::getProfilePostsForUserPaginated($user, $page, $perPage);
    }

    /**
     * @param \XF\Entity\User $user
     * @return \XF\Finder\ProfilePost
     */
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
