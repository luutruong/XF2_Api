<?php

namespace Truonglv\Api\Api\ControllerPlugin;

use XF\Api\ControllerPlugin\AbstractPlugin;
use XF\Mvc\Entity\Entity;

class Report extends AbstractPlugin
{
    public function actionReport($contentType, Entity $content)
    {
        $this->assertRequiredApiInput(['message']);

        $creator = $this->setupReportCreate($contentType, $content);
        if (!$creator->validate($errors)) {
            return $this->error($errors);
        }

        $creator->save();
        $this->finalizeReportCreate($creator);

        return $this->apiSuccess();
    }

    /**
     * @param \XF\Service\Report\Creator $creator
     */
    protected function finalizeReportCreate(\XF\Service\Report\Creator $creator)
    {
        $creator->sendNotifications();
    }

    /**
     * @param string $contentType
     * @param Entity $content
     *
     * @return \XF\Service\Report\Creator
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function setupReportCreate($contentType, Entity $content)
    {
        $message = $this->request->filter('message', 'str');
        if (!$message) {
            throw $this->exception($this->error(\XF::phrase('please_enter_reason_for_reporting_this_message')));
        }

        /** @var \XF\Service\Report\Creator $creator */
        $creator = $this->service('XF:Report\Creator', $contentType, $content);
        $creator->setMessage($message);

        return $creator;
    }
}