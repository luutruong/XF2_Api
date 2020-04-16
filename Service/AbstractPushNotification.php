<?php

namespace Truonglv\Api\Service;

use XF\Entity\User;
use XF\Entity\UserAlert;
use Truonglv\Api\Entity\Log;
use XF\Service\AbstractService;
use XF\Entity\ConversationMaster;
use XF\Entity\ConversationMessage;
use XF\Entity\ConversationRecipient;
use Truonglv\Api\XF\Service\Conversation\Pusher;

abstract class AbstractPushNotification extends AbstractService
{
    public function __construct(\XF\App $app)
    {
        parent::__construct($app);

        $this->setupDefaults();
    }

    /**
     * @param string $externalId
     * @param string $pushToken
     * @return void
     */
    abstract public function unsubscribe($externalId, $pushToken);

    /**
     * @return string
     */
    abstract protected function getProviderId();

    /**
     * @param mixed $subscriptions
     * @param string $title
     * @param string $body
     * @param array $data
     * @return void
     */
    abstract protected function doSendNotification($subscriptions, $title, $body, array $data);

    /**
     * @param UserAlert $alert
     * @return void
     */
    public function sendNotification(UserAlert $alert)
    {
        $subscriptions = $this->findSubscriptions()
            ->where('user_id', $alert->alerted_user_id)
            ->fetch();
        if (!$subscriptions->count()) {
            return;
        }

        /** @var \Truonglv\Api\XF\Entity\UserAlert $mixed */
        $mixed = $alert;

        $this->doSendNotification(
            $subscriptions,
            $this->app->options()->boardTitle,
            $this->getAlertContentBody($alert),
            $mixed->getTApiAlertData(true)
        );
    }

    /**
     * @param ConversationMessage $message
     * @param string $actionType
     * @return void
     * @throws \XF\PrintableException
     */
    public function sendConversationNotification(ConversationMessage $message, $actionType)
    {
        if (!in_array($actionType, ['create', 'reply'], true)) {
            return;
        }

        /** @var ConversationMaster $conversation */
        $conversation = $message->Conversation;

        $receivers = $conversation->getRelationFinder('Recipients')
            ->where('recipient_state', 'active')
            ->with(['User', 'User.Option'], true)
            ->fetch();
        if (!$receivers->count()) {
            return;
        }

        /** @var User $sender */
        $sender = $message->User;

        /** @var ConversationRecipient $receiver */
        foreach ($receivers as $receiver) {
            if ($receiver->User === null
                || $sender->user_id === $receiver->user_id
            ) {
                continue;
            }

            $subscriptions = $this->findSubscriptions()
                ->where('user_id', $receiver->User->user_id)
                ->fetch();
            if (!$subscriptions->count()) {
                continue;
            }

            $body = $this->getConversationPushContentBody($receiver->User, $message, $actionType);
            $this->doSendNotification(
                $subscriptions,
                $this->app->options()->boardTitle,
                $body,
                [
                    'content_type' => 'conversation_message',
                    'content_id' => $message->message_id
                ]
            );
        }
    }

    /**
     * @return \XF\Mvc\Entity\Finder
     */
    protected function findSubscriptions()
    {
        $finder = $this->app->finder('Truonglv\Api:Subscription');
        $finder->where('provider', $this->getProviderId());

        return $finder;
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
     * @return void
     * @throws \XF\PrintableException
     */
    protected function sendNotificationRequest($method, $endPoint, array $payload)
    {
        $response = null;

        try {
            $response = $this->client()->request($method, $endPoint, $payload);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            $this->app->logException($e, false, '[tl] Api: ');
        }

        if ($response === null) {
            return;
        }

        $this->logRequest(
            $method,
            $endPoint,
            $payload,
            $response->getStatusCode(),
            $response->getBody()->getContents()
        );
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

    /**
     * @param array $options
     * @return \GuzzleHttp\Client
     */
    protected function client(array $options = [])
    {
        return $this->app->http()->createClient(array_replace([
            'connect_timeout' => 5,
            'timeout' => 5,
        ], $options));
    }
}
