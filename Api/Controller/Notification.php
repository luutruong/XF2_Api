<?php

namespace Truonglv\Api\Api\Controller;

use XF\Entity\UserAlert;
use XF\Mvc\ParameterBag;
use XF\Api\Controller\AbstractController;

class Notification extends AbstractController
{
    public function actionGet(ParameterBag $params)
    {
        $this->assertRegisteredUser();

        /** @var UserAlert|null $alert */
        $alert = $this->finder('XF:UserAlert')->whereId($params->alert_id)->fetchOne();
        if (!$alert || $alert->alerted_user_id !== \XF::visitor()->user_id) {
            return $this->notFound();
        }

        /** @var \XF\Repository\UserAlert $alertRepo */
        $alertRepo = $this->repository('XF:UserAlert');
        $alertRepo->addContentToAlerts([$alert->alert_id => $alert]);

        if (!$alert->view_date) {
            $alert->view_date = \XF::$time;
            $alert->save();
        }

        return $this->apiResult([
            'notification' => $alert->toApiResult()
        ]);
    }
}
