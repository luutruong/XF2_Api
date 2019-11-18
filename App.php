<?php

namespace Truonglv\Api;

use XF\Http\Request;
use XF\Entity\Attachment;
use XF\Mvc\Entity\Entity;
use Truonglv\Api\Data\Reaction;
use XF\Api\Result\EntityResult;

class App
{
    const HEADER_KEY_APP_VERSION = 'HTTP_XF_TAPI_VERSION';
    const HEADER_KEY_API_KEY = 'HTTP_XF_TAPI_KEY';
    const HEADER_KEY_ACCESS_TOKEN = 'HTTP_XF_TAPI_TOKEN';

    const PARAM_KEY_INCLUDE_MESSAGE_HTML = 'include_message_html';

    /**
     * @var int
     */
    public static $followingPerPage = 20;
    /**
     * @var bool
     */
    public static $enableLogging = false;

    /**
     * @param Request|null $request
     * @return bool
     */
    public static function isRequestFromApp(Request $request = null)
    {
        $request = $request ?: \XF::app()->request();

        return trim($request->getServer(self::HEADER_KEY_APP_VERSION)) !== '';
    }

    /**
     * @return array
     */
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

    /**
     * @param Attachment $attachment
     * @return string
     */
    public static function buildAttachmentLink(Attachment $attachment)
    {
        return \XF::app()->router('public')->buildLink('canonical:attachments', $attachment, [
            'hash' => (strlen($attachment->temp_hash) > 0) ? $attachment->temp_hash : null,
            'tapi_token' => self::generateTokenForViewingAttachment($attachment)
        ]);
    }

    /**
     * @param Attachment $attachment
     * @return string
     */
    public static function generateTokenForViewingAttachment(Attachment $attachment)
    {
        $app = \XF::app();
        $apiKey = $app->request()->getServer(self::HEADER_KEY_API_KEY);

        return \XF::$time . '.' . md5(
            \XF::$time
                . $apiKey
                . $attachment->attachment_id
                . $app->config('globalSalt')
            );
    }

    /**
     * @param EntityResult $result
     * @param Entity $entity
     * @param string $reactionKey
     * @return void
     */
    public static function attachReactions(EntityResult $result, Entity $entity, $reactionKey = 'reactions')
    {
        /** @var callable $callable */
        $callable = [$entity, 'getVisitorReactionId'];
        $visitorReactedId = call_user_func($callable);

        $reacted = [];
        $entityReactions = $entity->get($reactionKey . '_');
        if (is_array($entityReactions)) {
            /** @var Reaction $reactionData */
            $reactionData = \XF::app()->data('Truonglv\Api:Reaction');
            $reactions = $reactionData->getReactions();

            foreach (array_keys($entityReactions) as $reactionId) {
                $count = $entityReactions[$reactionId];
                if ($visitorReactedId > 0 && $visitorReactedId == $reactionId) {
                    $count -= 1;
                }
                if (isset($reactions[$reactionId]) && $count > 0) {
                    $reacted[] = $reactions[$reactionId]['imageUrl'];
                }
            }
        }

        $result->tapi_reactions = $reacted;
    }

    /**
     * @param EntityResult $result
     * @param Entity $entity
     * @param string $messageKey
     * @return void
     */
    public static function includeMessageHtmlIfNeeded(EntityResult $result, Entity $entity, $messageKey = 'message')
    {
        $isInclude = (bool) $entity->app()->request()->filter(self::PARAM_KEY_INCLUDE_MESSAGE_HTML, 'bool');
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

        $stringFomatter = $entity->app()->stringFormatter();
        $plainText = $stringFomatter->stripBbCode($entity->get($messageKey), [
            'stripQuote' => true
        ]);

        $result->tapi_message_plain_text = $plainText;
        $result->tapi_message_plain_text_preview = $stringFomatter->wholeWordTrim(
            $plainText,
            $entity->app()->options()->tApi_discussionPreviewLength
        );
    }
}
