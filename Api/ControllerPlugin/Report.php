<?php

namespace Truonglv\Api\Api\ControllerPlugin;

use XF;
use XF\Mvc\Entity\Entity;
use XF\Api\ControllerPlugin\AbstractPlugin;

class Report extends AbstractPlugin
{
    /**
     * @param string $contentType
     * @param Entity $content
     * @return \XF\Api\Mvc\Reply\ApiResult|\XF\Mvc\Reply\Error
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionReport($contentType, Entity $content)
    {
        $this->assertRequiredApiInput(['message']);

        $creator = $this->setupReportCreate($contentType, $content);
        if (!$creator->validate($errors)) {
            return $this->error($errors);
        }

        $creator->save();
        $this->finalizeReportCreate($creator);

        return $this->apiSuccess([
            'message' => XF::phrase('thank_you_for_reporting_this_content')
        ]);
    }

    /**
     * @param \XF\Service\Report\Creator $creator
     * @return void
     */
    protected function finalizeReportCreate(\XF\Service\Report\Creator $creator)
    {
        $creator->sendNotifications();
    }

    /**
     * @param string $contentType
     * @param Entity $content
     * @return \XF\Service\Report\Creator
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function setupReportCreate($contentType, Entity $content)
    {
        $message = $this->request->filter('message', 'str');
        if (!$message) {
            throw $this->exception($this->error(XF::phrase('please_enter_reason_for_reporting_this_message')));
        }

        /** @var \XF\Service\Report\Creator $creator */
        $creator = $this->service('XF:Report\Creator', $contentType, $content);
        $creator->setMessage($message);

        return $creator;
    }
}
