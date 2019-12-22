<?php

namespace Truonglv\Api\Service;

use XF\Entity\User;
use XF\Entity\UserAlert;
use Truonglv\Api\Entity\Log;
use XF\Service\AbstractService;
use XF\Entity\ConversationMessage;
use Truonglv\Api\XF\Service\Conversation\Pusher;

abstract class AbstractPushNotification extends AbstractService
{
    public function __construct(\XF\App $app)
    {
        parent::__construct($app);

        $this->setupDefaults();
    }

    /**
     * @param ConversationMessage $message
     * @param string $actionType
     * @return void
     */
    abstract public function sendConversationNotification(ConversationMessage $message, $actionType);

    /**
     * @param UserAlert $alert
     * @return void
     */
    abstract public function sendNotification(UserAlert $alert);

    /**
     * @param string $externalId
     * @param string $pushToken
     * @return void
     */
    abstract public function unsubscribe($externalId, $pushToken);

    /**
     * @return \XF\Mvc\Entity\Finder
     */
    protected function findSubscriptions()
    {
        return $this->app->finder('Truonglv\Api:Subscription');
    }

    /**
     * @return void
     */
    protected function setupDefaults()
    {
    }

    /**
     * @param UserAlert $alert
     * @return string
     */
    protected function getAlertContentBody(UserAlert $alert)
    {
        /** @var \Truonglv\Api\XF\Service\Alert\Pusher $pusher */
        $pusher = $this->service('XF:Alert\Pusher', $alert->Receiver, $alert);

        return $pusher->tApiGetNotificationBody();
    }

    /**
     * @param User $receiver
     * @param ConversationMessage $message
     * @param string $actionType
     * @return string
     */
    protected function getConversationPushContentBody(User $receiver, ConversationMessage $message, $actionType)
    {
        /** @var Pusher $pusher */
        $pusher = $this->service('XF:Conversation\Pusher', $receiver, $message, $actionType, $message->User);

        return $pusher->tApiGetNotificationBody();
    }

    /**
     * @param string $method
     * @param string $endPoint
     * @param array $payload
     * @param int $responseCode
     * @param mixed $response
     * @param array $extra
     * @throws \XF\PrintableException
     * @return void
     */
    protected function logRequest($method, $endPoint, array $payload, $responseCode, $response, array $extra = [])
    {
        $extra = \array_replace([
            'app_version' => '',
            'user_id' => 0
        ], $extra);

        /** @var Log $log */
        $log = $this->app->em()->create('Truonglv\Api:Log');
        $log->payload = $payload;
        $log->end_point = $endPoint;
        $log->method = \strtoupper($method);
        $log->response_code = $responseCode;
        $log->response = $response;

        $log->bulkSet($extra);

        $log->save();
    }
}
