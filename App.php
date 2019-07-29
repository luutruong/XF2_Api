<?php

namespace Truonglv\Api;

use XF\Entity\Attachment;
use XF\Mvc\Entity\Entity;
use XF\Api\Result\EntityResult;

class App
{
    const HEADER_KEY_APP_VERSION = 'HTTP_XF_TAPI_VERSION';
    const HEADER_KEY_DEVICE_INFO = 'HTTP_XF_TAPI_DEVICE_INFO';

    public static $followingPerPage = 20;
    public static $enableLogging = false;

    public static function getSupportAlertContentTypes()
    {
        return [
            'conversation',
            'conversation_message',
            'post',
            'thread',
            'user'
        ];
    }

    public static function generateTokenForViewingAttachment(Attachment $attachment)
    {
        $app = \XF::app();
        $apiKey = $app->request()->getApiKey();

        return \XF::$time . '.' . md5(
                \XF::$time
                . $apiKey
                . $attachment->attachment_id
                . $app->config('globalSalt')
            );
    }

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
