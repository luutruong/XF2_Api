<?php

namespace Truonglv\Api\XF\Api\Controller;

use XF\Mvc\Entity\Entity;
use XF\Service\User\Ignore;

class Me extends XFCP_Me
{
    public function actionGetIgnoring()
    {
        $visitor = \XF::visitor();
        if ($ignored = $visitor->Profile->ignored) {
            $users = $this->finder('XF:User')
                ->where('user_id', array_keys($ignored))
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

    public function actionGetNotifications()
    {
        $visitor = \XF::visitor();

        $page = $this->filterPage();
        $perPage = $this->options()->alertsPerPage;

        /** @var \XF\Repository\UserAlert $alertRepo */
        $alertRepo = $this->repository('XF:UserAlert');

        $alertsFinder = $alertRepo->findAlertsForUser($visitor->user_id);
        $total = $alertsFinder->total();
        $alerts = $alertsFinder->limitByPage($page, $perPage)->fetch();

        $alertRepo->addContentToAlerts($alerts);
        $alerts = $alerts->filterViewable();

        $data = [
            'alerts' => $alerts->toApiResults(Entity::VERBOSITY_VERBOSE),
            'pagination' => $this->getPaginationData($alerts, $page, $perPage, $total)
        ];

        return $this->apiResult($data);
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
