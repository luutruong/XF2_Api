<?php

namespace Truonglv\Api\Api\Controller;

use XF;
use XF\Util\Arr;
use function count;
use function strpos;
use Truonglv\Api\App;
use function in_array;
use function array_map;
use function array_keys;
use XF\Api\Controller\AbstractController;

class NotificationsController extends AbstractController
{
    public function actionGet()
    {
        $this->assertRegisteredUser();

        $visitor = XF::visitor();

        $page = $this->filterPage();
        $perPage = $this->options()->tApi_recordsPerPage;

        $supportedContentTypes = App::getSupportAlertContentTypes();
        $contentType = $this->filter('content_type', 'str');
        $contentTypes = $supportedContentTypes;

        if ($contentType !== '') {
            if (strpos($contentType, ',') === false) {
                if (in_array($contentType, $supportedContentTypes, true)) {
                    $contentTypes = [$contentType];
                } else {
                    $contentTypes = [];
                }
            } else {
                $contentTypes = Arr::stringToArray($contentType, '/,/');
                $contentTypes = array_map('trim', $contentTypes);
                foreach (array_keys($contentTypes) as $key) {
                    if (!in_array($contentTypes[$key], $supportedContentTypes, true)) {
                        unset($contentTypes[$key]);
                    }
                }
            }
        }

        $alertRepo = $this->repository(XF\Repository\UserAlertRepository::class);

        $alertsFinder = $alertRepo->findAlertsForUser($visitor->user_id);
        if (count($contentTypes) === 0) {
            $alertsFinder->whereImpossible();
        } else {
            $alertsFinder->where('content_type', $contentTypes);
        }

        if ($this->filter('unread', 'bool') === true) {
            $alertsFinder->where('read_date', 0);
        }

        $total = $alertsFinder->total();
        /** @var mixed $alerts */
        $alerts = $alertsFinder->limitByPage($page, $perPage)->fetch();

        $alertRepo->addContentToAlerts($alerts);
        if (XF::isApiCheckingPermissions()) {
            $alerts = $alerts->filterViewable();
        }

        $data = [
            'notifications' => $alerts->toApiResults(),
            'pagination' => $this->getPaginationData($alerts, $page, $perPage, $total)
        ];

        return $this->apiResult($data);
    }

    public function actionPostViewed()
    {
        $this->assertRegisteredUser();

        $alertRepo = $this->repository(XF\Repository\UserAlertRepository::class);
        $alertRepo->markUserAlertsViewed(XF::visitor());

        return $this->apiSuccess();
    }

    public function actionPostRead()
    {
        $this->assertRegisteredUser();

        $alertRepo = $this->repository(XF\Repository\UserAlertRepository::class);
        $alertRepo->markUserAlertsRead(XF::visitor());

        return $this->apiSuccess();
    }
}
