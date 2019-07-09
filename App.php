<?php

namespace Truonglv\Api;

use XF\Mvc\Entity\Entity;
use XF\Api\Result\EntityResult;

class App
{
    /**
     * @var int minutes
     */
    public static $attachmentTokenExpires = 30;

    public static function includeMessageHtmlIfNeeded(EntityResult $result, Entity $entity, $messageKey = 'message')
    {
        $isInclude = $entity->app()->request()->filter('include_message_html', 'bool');
        if (!$isInclude) {
            return;
        }

        $bbCode = $entity->app()->bbCode();
        $result->tapi_message_html = $bbCode->render(
            $entity->get($messageKey),
            'Truonglv\Api:SimpleHtml',
            'tapi:html',
            $entity
        );
    }
}
