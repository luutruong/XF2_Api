<?php

namespace Truonglv\Api\Api\Controller;

use Truonglv\Api\App;
use XF\Api\Controller\AbstractController;

class Notifications extends AbstractController
{
    public function actionGet()
    {
        $this->assertRegisteredUser();

        $visitor = \XF::visitor();

        $page = $this->filterPage();
        $perPage = $this->options()->tApi_recordsPerPage;

        /** @var \XF\Repository\UserAlert $alertRepo */
        $alertRepo = $this->repository('XF:UserAlert');

        $alertsFinder = $alertRepo->findAlertsForUser($visitor->user_id);
        $alertsFinder->where('content_type', App::getSupportAlertContentTypes());

        $total = $alertsFinder->total();
        /** @var mixed $alerts */
        $alerts = $alertsFinder->limitByPage($page, $perPage)->fetch();

        $alertRepo->addContentToAlerts($alerts);
        if (\XF::isApiCheckingPermissions()) {
            $alerts = $alerts->filterViewable();
        }

        $data = [
            'notifications' => $alerts->toApiResults(),
            'pagination' => $this->getPaginationData($alerts, $page, $perPage, $total)
        ];

        return $this->apiResult($data);
    }
}
