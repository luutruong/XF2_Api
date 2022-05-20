<?php

namespace Truonglv\Api;

use XF\Http\Request;
use XF\Entity\Attachment;
use XF\Mvc\Entity\Entity;
use Truonglv\Api\Data\Reaction;
use XF\Api\Result\EntityResult;
use Truonglv\Api\Util\Encryption;
use Truonglv\Api\Repository\AlertQueue;

class App
{
    const HEADER_KEY_APP_VERSION = 'HTTP_XF_TAPI_VERSION';
    const HEADER_KEY_API_KEY = 'HTTP_XF_TAPI_KEY';
    const HEADER_KEY_ACCESS_TOKEN = 'HTTP_XF_TAPI_TOKEN';

    const KEY_LINK_PROXY_ACCESS_TOKEN = 'token';
    const KEY_LINK_PROXY_DATE = 'date';
    const KEY_LINK_PROXY_TARGET_URL = 'url';
    const KEY_LINK_PROXY_INPUT_DATA = '_d';
    const KEY_LINK_PROXY_INPUT_SIGNATURE = '_s';

    const QUOTE_PLACEHOLDER_TEMPLATE = '[QUOTE={content_type},{content_id}]';

    /**
     * @var bool
     */
    public static $enableLogging = false;

    /**
     * @var string
     */
    public static $defaultPushNotificationService = 'Truonglv\Api:FCM';

    /**
     * @var \XF\Http\Request|null
     */
    protected static $request;

    public static function setRequest(?\XF\Http\Request $request): void
    {
        self::$request = $request;
    }

    public static function getRequest(): \XF\Http\Request
    {
        return self::$request !== null ? self::$request : \XF::app()->request();
    }

    /**
     * @param string $targetUrl
     * @return string
     */
    public static function buildLinkProxy(string $targetUrl): string
    {
        $accessToken = static::getRequest()->getServer(static::HEADER_KEY_ACCESS_TOKEN);

        $payload = [
            self::KEY_LINK_PROXY_ACCESS_TOKEN => $accessToken,
            self::KEY_LINK_PROXY_DATE => \XF::$time,
            self::KEY_LINK_PROXY_TARGET_URL => $targetUrl,
        ];

        $encoded = \strval(\json_encode($payload));

        try {
            $encrypted = Encryption::encrypt($encoded, \XF::app()->options()->tApi_encryptKey);
        } catch (\InvalidArgumentException $e) {
            return $targetUrl;
        }

        return \XF::app()->router('public')
            ->buildLink('canonical:misc/tapi-goto', null, [
                self::KEY_LINK_PROXY_INPUT_DATA => $encrypted,
                self::KEY_LINK_PROXY_INPUT_SIGNATURE => \md5($encoded)
            ]);
    }

    /**
     * @return array
     */
    public static function getSupportAlertContentTypes(): array
    {
        /** @var AlertQueue $alertQueueRepo */
        $alertQueueRepo = \XF::repository('Truonglv\Api:AlertQueue');

        return $alertQueueRepo->getSupportedAlertContentTypes();
    }

    /**
     * @param Attachment $attachment
     * @return string
     */
    public static function buildAttachmentLink(Attachment $attachment): string
    {
        return \XF::app()->router('public')->buildLink('canonical:attachments', $attachment);
    }

    /**
     * @param EntityResult $result
     * @param Entity $entity
     * @param string $reactionKey
     * @return void
     */
    public static function attachReactions(EntityResult $result, Entity $entity, string $reactionKey = 'reactions'): void
    {
        /** @var callable $callable */
        $callable = [$entity, 'getVisitorReactionId'];
        $visitorReactedId = \call_user_func($callable);

        $reacted = [];
        $entityReactions = $entity->get($reactionKey . '_');
        if (\is_array($entityReactions)) {
            /** @var Reaction $reactionData */
            $reactionData = \XF::app()->data('Truonglv\Api:Reaction');
            $reactions = $reactionData->getReactions();

            foreach (\array_keys($entityReactions) as $reactionId) {
                $count = $entityReactions[$reactionId];
                if ($visitorReactedId > 0 && $visitorReactedId == $reactionId) {
                    $count -= 1;
                }
                if (isset($reactions[$reactionId]) && $count > 0) {
                    $reacted[] = [
                        'image' => $reactions[$reactionId]['imageUrl'],
                        'total' => $count
                    ];
                }
            }
        }

        $result->tapi_reactions = $reacted;
    }

    public static function alertQueueRepo(): AlertQueue
    {
        /** @var AlertQueue $repo */
        $repo = \XF::repository('Truonglv\Api:AlertQueue');

        return $repo;
    }
}
