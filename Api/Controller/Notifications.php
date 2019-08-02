<?php

namespace Truonglv\Api\Api\Controller;

use Truonglv\Api\App;
use XF\Mvc\Entity\Entity;
use XF\Api\Controller\AbstractController;

class Notifications extends AbstractController
{
    public function actionGet()
    {
        $this->assertRegisteredUser();

        $visitor = \XF::visitor();

        $page = $this->filterPage();
        $perPage = $this->options()->alertsPerPage;

        /** @var \XF\Repository\UserAlert $alertRepo */
        $alertRepo = $this->repository('XF:UserAlert');

        $alertsFinder = $alertRepo->findAlertsForUser($visitor->user_id);
        $alertsFinder->where('content_type', App::getSupportAlertContentTypes());

        $total = $alertsFinder->total();
        $alerts = $alertsFinder->limitByPage($page, $perPage)->fetch();

        $alertRepo->addContentToAlerts($alerts);
        $alerts = $alerts->filterViewable();

        $data = [
            'notifications' => $alerts->toApiResults(Entity::VERBOSITY_VERBOSE),
            'pagination' => $this->getPaginationData($alerts, $page, $perPage, $total)
        ];

        return $this->apiResult($data);
    }
}
