<?php

namespace Truonglv\Api\XF\Api\Controller;

use XF;
use function count;
use function array_keys;
use XF\Mvc\Entity\Entity;
use XF\Api\Mvc\Reply\ApiResult;
use Truonglv\Api\Repository\TokenRepository;

class MeController extends XFCP_MeController
{
    public function actionGetIgnoring()
    {
        $visitor = XF::visitor();
        $ignored = $visitor->Profile !== null ? $visitor->Profile->ignored : [];
        if (count($ignored) > 0) {
            $users = $this->finder(XF\Finder\UserFinder::class)
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

        $visitor = XF::visitor();
        if (XF::isApiCheckingPermissions() && !$visitor->canIgnoreUser($user)) {
            return $this->noPermission();
        }

        if (!$visitor->isIgnoring($user->user_id)) {
            $ignore = $this->service(XF\Service\User\IgnoreService::class, $user);
            $ignore->ignore();
        }

        return $this->apiSuccess();
    }

    public function actionDeleteIgnoring()
    {
        $this->assertRequiredApiInput(['user_id']);

        $userId = $this->filter('user_id', 'uint');
        $user = $this->tApiAssertViewableUser($userId);

        $visitor = XF::visitor();
        if (XF::isApiCheckingPermissions() && !$visitor->canIgnoreUser($user)) {
            return $this->noPermission();
        }

        if ($visitor->isIgnoring($user->user_id)) {
            $ignore = $this->service(XF\Service\User\IgnoreService::class, $user);
            $ignore->unignore();
        }

        return $this->apiSuccess();
    }

    public function actionGetWatchedThreads()
    {
        $page = $this->filterPage();
        $perPage = $this->options()->tApi_recordsPerPage;

        $visitor = XF::visitor();

        $threadRepo = $this->repository(XF\Repository\ThreadRepository::class);
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
        if (XF::isApiCheckingPermissions()) {
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
            $result['user'] = XF::visitor()->toApiResult();

            return $this->apiResult($result);
        }

        return $response;
    }

    public function actionDelete()
    {
        /** @var \Truonglv\Api\XF\Entity\User $visitor */
        $visitor = XF::visitor();
        if (XF::isApiCheckingPermissions() && !$visitor->canTapiDelete($error)) {
            return $this->noPermission($error);
        }

        $deleter = $this->service(XF\Service\User\DeleteService::class, $visitor);
        $deleter->renameTo('guest-' . time());
        /** @var \Truonglv\Api\XF\Entity\User $user */
        $user = $deleter->getUser();
        $user->setOption('allow_self_delete', true);
        // XF bug: https://xenforo.com/community/threads/service-xf-service-user-delete-prevent-delete-self-account.202718/
        $user->setTapiAllowSelfDelete(true);

        if (!$deleter->delete($errors)) {
            return $this->error($errors);
        }

        $tokenRepo = $this->repository(TokenRepository::class);
        $tokenRepo->deleteUserTokens($visitor->user_id);

        return $this->apiSuccess();
    }

    public function actionPostUsername()
    {
        $this->assertRequiredApiInput(['username']);

        $visitor = XF::visitor();
        if (XF::isApiCheckingPermissions() && !$visitor->canChangeUsername($error)) {
            return $this->noPermission($error);
        }

        $service = $this->service(XF\Service\User\UsernameChangeService::class, XF::visitor());

        $service->setNewUsername($this->filter('username', 'str'));
        $reason = $this->filter('change_reason', 'str');
        if ($this->options()->usernameChangeRequireReason > 0 && strlen($reason) === 0) {
            throw $this->exception($this->error(XF::phrase('please_provide_reason_for_this_username_change')));
        }
        $service->setChangeReason($reason);

        if (!$service->validate($errors)) {
            return $this->error($errors);
        }

        /** @var \XF\Entity\UsernameChange $usernameChange */
        $usernameChange = $service->save();

        if ($usernameChange->change_state == 'approved') {
            return $this->apiSuccess([
                'message' => XF::phrase('your_username_has_been_changed_successfully'),
                'changeState' => $usernameChange->change_state,
            ]);
        }

        return $this->apiSuccess([
            'message' => XF::phrase('your_username_change_must_be_approved_by_moderator'),
            'changeState' => $usernameChange->change_state,
        ]);
    }

    public function actionPostCover()
    {
        $this->assertRequiredApiFile('file');

        $visitor = XF::visitor();
        if (!$visitor->canUploadProfileBanner()) {
            return $this->noPermission();
        }

        $bannerService = $this->service(XF\Service\User\ProfileBannerService::class, $visitor);

        $upload = $this->request->getFile('file', false, false);
        if (!$bannerService->setImageFromUpload($upload)) {
            return $this->error($bannerService->getError());
        }

        if (!$bannerService->updateBanner()) {
            return $this->error(XF::phrase('new_banner_could_not_be_processed'));
        }

        return $this->apiResult([
            'user' => $visitor->toApiResult(),
        ]);
    }

    public function actionDeleteCover()
    {
        $visitor = XF::visitor();
        if (!$visitor->canUploadProfileBanner()) {
            return $this->noPermission();
        }

        $bannerService = $this->service(XF\Service\User\ProfileBannerService::class, $visitor);
        $bannerService->deleteBanner();

        return $this->apiSuccess();
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
    protected function tApiAssertViewableUser($id, $with = 'api', bool $basicProfileOnly = true)
    {
        /** @var \XF\Entity\User $user */
        $user = $this->assertRecordExists('XF:User', $id, $with);

        if (XF::isApiCheckingPermissions()) {
            $canView = $basicProfileOnly ? $user->canViewBasicProfile($error) : $user->canViewFullProfile($error);
            if (!$canView) {
                throw $this->exception($this->noPermission($error));
            }
        }

        return $user;
    }
}
