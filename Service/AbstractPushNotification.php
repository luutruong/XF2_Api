<?php

namespace Truonglv\Api\Service;

use GuzzleHttp\Client;
use XF;
use XF\Entity\User;
use Truonglv\Api\App;
use function strtoupper;
use XF\Entity\UserAlert;
use Truonglv\Api\Entity\Log;
use function array_key_exists;
use XF\Service\AbstractService;
use XF\Entity\ConversationMaster;
use XF\Entity\ConversationMessage;
use XF\Entity\ConversationRecipient;
use Truonglv\Api\XF\Service\Conversation\Pusher;

abstract class AbstractPushNotification extends AbstractService
{
    /**
     * @var array
     */
    private array $userAlertsCache = [];

    public function __construct(\XF\App $app)
    {
        parent::__construct($app);

        $this->setupDefaults();
    }

    abstract public function unsubscribe(string $externalId, string $pushToken): void;

    abstract protected function getProviderId(): string;

    abstract protected function doSendNotification(XF\Mvc\Entity\AbstractCollection $subscriptions, string $title, string $body, array $data): void;

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

    public function sendConversationNotification(ConversationMessage $message, string $actionType): void
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

    protected function getTotalUnviewedNotifications(User $user): int
    {
        if (!array_key_exists($user->user_id, $this->userAlertsCache)) {
            /** @var \XF\Repository\UserAlert $alertRepo */
            $alertRepo = XF::app()->repository('XF:UserAlert');

            $total = $alertRepo->findAlertsForUser($user->user_id)
                ->where('view_date', 0)
                ->where('content_type', App::getSupportAlertContentTypes())
                ->total();
            $this->userAlertsCache[$user->user_id] = $total + $user->conversations_unread;
        }

        return $this->userAlertsCache[$user->user_id];
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
    protected function setupDefaults(): void
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

    protected function getConversationPushContentBody(User $receiver, ConversationMessage $message, string $actionType): string
    {
        /** @var Pusher $pusher */
        $pusher = $this->service('XF:Conversation\Pusher', $receiver, $message, $actionType, $message->User);

        return $pusher->tApiGetNotificationBody();
    }

    protected function sendNotificationRequest(string $method, string $endPoint, array $payload): void
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

    protected function logRequest(string $method, string $endPoint, array $payload, int $responseCode, $response, array $extra = []): void
    {
        $extra = \array_replace([
            'app_version' => '',
            'user_id' => 0
        ], $extra);

        /** @var Log $log */
        $log = $this->app->em()->create('Truonglv\Api:Log');
        $log->payload = $payload;
        $log->end_point = $endPoint;
        $log->method = strtoupper($method);
        $log->response_code = $responseCode;
        $log->response = $response;

        $log->bulkSet($extra);

        $log->save();
    }

    protected function client(array $options = []): Client
    {
        return $this->app->http()->createClient(array_replace([
            'connect_timeout' => 5,
            'timeout' => 5,
        ], $options));
    }
}
