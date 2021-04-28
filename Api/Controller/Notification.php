<?php

namespace Truonglv\Api\Api\Controller;

use XF\Entity\UserAlert;
use XF\Mvc\ParameterBag;
use XF\Mvc\Entity\Entity;
use XF\Api\Controller\AbstractController;

class Notification extends AbstractController
{
    public function actionGet(ParameterBag $params)
    {
        $this->assertRegisteredUser();
        $alert = $this->assertViewableAlert($params['alert_id']);

        if (!\in_array($alert->content_type, \Truonglv\Api\App::getSupportAlertContentTypes(), true)) {
            return $this->notFound();
        }

        $handler = $alert->getHandler();
        if ($handler === null) {
            return $this->noPermission();
        }

        /** @var Entity|null $content */
        $content = $this->app()->findByContentType(
            $alert->content_type,
            $alert->content_id,
            $handler->getEntityWith()
        );

        return $this->apiResult([
            'notification' => $alert->toApiResult(Entity::VERBOSITY_VERBOSE),
            'content' => $content === null
                ? null
                : $content->toApiResult(Entity::VERBOSITY_VERBOSE, $this->getContentApiResultOptions($alert))
        ]);
    }

    public function actionPostMarkRead(ParameterBag $params)
    {
        $alert = $this->assertViewableAlert($params['alert_id']);

        if ($alert->view_date <= 0) {
            $alert->view_date = \XF::$time;
            $alert->save();
        }

        return $this->apiSuccess();
    }

    protected function getContentApiResultOptions(UserAlert $userAlert): array
    {
        switch ($userAlert->content_type) {
            case 'post':
                return [
                    'with_thread' => true,
                ];
            case 'conversation_message':
                return [
                    'with_conversation' => true,
                ];
        }

        return [];
    }

    /**
     * @param mixed $alertId
     * @return UserAlert
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertViewableAlert($alertId): UserAlert
    {
        /** @var UserAlert $alert */
        $alert = $this->assertRecordExists('XF:UserAlert', $alertId);
        if ($alert->alerted_user_id !== \XF::visitor()->user_id) {
            throw $this->exception($this->noPermission());
        }

        return $alert;
    }
}
