<?php

namespace Truonglv\Api\XF\Api\Controller;

use XF;
use function in_array;
use XF\Mvc\ParameterBag;
use XF\Mvc\Entity\Entity;
use XF\Service\User\Follow;
use XF\Repository\UserFollow;
use XF\Api\Result\EntityResults;
use XF\Mvc\Entity\AbstractCollection;
use Truonglv\Api\Api\ControllerPlugin\ReportPlugin;

class UserController extends XFCP_UserController
{
    public function actionPostFollowing(ParameterBag $params)
    {
        $user = $this->assertViewableUser($params->user_id);
        $visitor = XF::visitor();
        if (XF::isApiCheckingPermissions() && !$visitor->canFollowUser($user)) {
            return $this->noPermission();
        }

        if (!$visitor->isFollowing($user)) {
            $follow = $this->service(XF\Service\User\FollowService::class, $user);
            $follow->follow();
        }

        return $this->apiSuccess();
    }

    public function actionGetFollowing(ParameterBag $params)
    {
        $user = $this->assertViewableUser($params->user_id);

        $page = $this->filterPage();
        $perPage = $this->app()->options()->tApi_recordsPerPage;

        $order = $this->filter('order', 'str');
        $direction = $this->filter('direction', 'str');
        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $followRepo = $this->repository(XF\Repository\UserFollowRepository::class);
        $finder = $followRepo->findFollowingForProfile($user);

        if ($order == 'last_activity') {
            $finder->order('User.last_activity', $direction);
        }

        $total = $finder->total();
        $entities = $total > 0
            ? $finder->limitByPage($page, $perPage)->fetch()
            : [];
        $users = [];

        /** @var \XF\Entity\UserFollow $entity */
        foreach ($entities as $entity) {
            $users[$entity->follow_user_id] = $entity->FollowUser;
        }

        return $this->apiResult([
            'users' => $this->em()->getBasicCollection($users)->toApiResults(Entity::VERBOSITY_VERBOSE),
            'pagination' => $this->getPaginationData($entities, $page, $perPage, $total)
        ]);
    }

    public function actionDeleteFollowing(ParameterBag $params)
    {
        $user = $this->assertViewableUser($params->user_id);
        $visitor = XF::visitor();
        if (XF::isApiCheckingPermissions() && !$visitor->canFollowUser($user)) {
            return $this->noPermission();
        }

        if ($visitor->isFollowing($user)) {
            $follow = $this->service(XF\Service\User\FollowService::class, $user);
            $follow->unfollow();
        }

        return $this->apiSuccess();
    }

    public function actionPostReport(ParameterBag $params)
    {
        $user = $this->assertViewableUser($params->user_id);
        if (XF::isApiCheckingPermissions() && !$user->canBeReported()) {
            return $this->noPermission();
        }

        $reportPlugin = $this->plugin(ReportPlugin::class);

        return $reportPlugin->actionReport('user', $user);
    }

    public function actionGetThreads(ParameterBag $params)
    {
        $user = $this->assertViewableUser($params->user_id);

        $threadRepo = $this->repository(XF\Repository\ThreadRepository::class);

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

    protected function setupTApiThreadFinder(\XF\Finder\ThreadFinder $finder, \XF\Entity\User $user): void
    {
        $finder->with('api');

        if (XF::isApiCheckingPermissions()) {
            $forumRepo = $this->repository(XF\Repository\ForumRepository::class);
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
        if ($perPage === null) {
            $perPage = $this->options()->tApi_recordsPerPage;
        }

        return parent::getProfilePostsForUserPaginated($user, $page, $perPage);
    }
}
