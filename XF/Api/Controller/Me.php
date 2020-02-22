<?php

namespace Truonglv\Api\XF\Api\Controller;

use XF\Api\Mvc\Reply\ApiResult;
use XF\Mvc\Entity\Entity;
use XF\Service\User\Ignore;

class Me extends XFCP_Me
{
    public function actionGet()
    {
        if (\XF::visitor()->user_id <= 0) {
            return $this->notFound();
        }

        return parent::actionGet();
    }

    public function actionGetIgnoring()
    {
        $visitor = \XF::visitor();
        $ignored = $visitor->Profile->ignored;
        if (\count($ignored) > 0) {
            $users = $this->finder('XF:User')
                ->where('user_id', \array_keys($ignored))
                ->order('username')
                ->fetch();
        } else {
            $users = $this->em()->getEmptyCollection();
        }

        return $this->apiResult([
            'users' => $users->toApiResults(Entity::VERBOSITY_VERBOSE)
        ]);
    }

    public function actionPostIgnoring()
    {
        $this->assertRequiredApiInput(['user_id']);

        $userId = $this->filter('user_id', 'uint');
        $user = $this->tApiAssertViewableUser($userId);

        $visitor = \XF::visitor();
        if (\XF::isApiCheckingPermissions() && !$visitor->canIgnoreUser($user)) {
            return $this->noPermission();
        }

        if (!$visitor->isIgnoring($user->user_id)) {
            /** @var Ignore $ignore */
            $ignore = $this->service('XF:User\Ignore', $user);
            $ignore->ignore();
        }

        return $this->apiSuccess();
    }

    public function actionDeleteIgnoring()
    {
        $this->assertRequiredApiInput(['user_id']);

        $userId = $this->filter('user_id', 'uint');
        $user = $this->tApiAssertViewableUser($userId);

        $visitor = \XF::visitor();
        if (\XF::isApiCheckingPermissions() && !$visitor->canIgnoreUser($user)) {
            return $this->noPermission();
        }

        if ($visitor->isIgnoring($user->user_id)) {
            /** @var Ignore $ignore */
            $ignore = $this->service('XF:User\Ignore', $user);
            $ignore->unignore();
        }

        return $this->apiSuccess();
    }

    public function actionGetWatchedThreads()
    {
        $page = $this->filterPage();
        $perPage = $this->options()->discussionsPerPage;

        $visitor = \XF::visitor();

        /** @var \XF\Repository\Thread $threadRepo */
        $threadRepo = $this->repository('XF:Thread');
        $threadFinder = $threadRepo->findThreadsForApi();

        $threadFinder->with('Watch|' . $visitor->user_id, true);
        $threadFinder->with('fullForum');
        $threadFinder->where('discussion_state', 'visible');
        $threadFinder->setDefaultOrder('last_post_date', 'DESC');

        $total = $threadFinder->total();

        $this->assertValidApiPage($page, $perPage, $total);

        $threads = $total > 0
            ? $threadFinder->limitByPage($page, $perPage)->fetch()
            : $this->em()->getEmptyCollection();
        if (\XF::isApiCheckingPermissions()) {
            // only filtered to the forums we could view -- could still be other conditions
            $threads = $threads->filterViewable();
        }

        $data = [
            'threads' => $threads->toApiResults(Entity::VERBOSITY_VERBOSE),
            'pagination' => $this->getPaginationData($threads, $page, $perPage, $total)
        ];

        return $this->apiResult($data);
    }

    public function actionPostAvatar()
    {
        $response = parent::actionPostAvatar();
        if ($response instanceof ApiResult) {
            $result = $response->getApiResult()->render();
            $result['user'] = \XF::visitor()->toApiResult();

            return $this->apiResult($result);
        }

        return $response;
    }

    /**
     * @param int $id
     * @param mixed $with
     * @param bool $basicProfileOnly
     *
     * @return \XF\Entity\User
     *
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function tApiAssertViewableUser($id, $with = 'api', $basicProfileOnly = true)
    {
        /** @var \XF\Entity\User $user */
        $user = $this->assertRecordExists('XF:User', $id, $with);

        if (\XF::isApiCheckingPermissions()) {
            $canView = $basicProfileOnly ? $user->canViewBasicProfile($error) : $user->canViewFullProfile($error);
            if (!$canView) {
                throw $this->exception($this->noPermission($error));
            }
        }

        return $user;
    }
}
