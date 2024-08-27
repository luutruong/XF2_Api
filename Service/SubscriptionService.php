<?php

namespace Truonglv\Api\Service;

use XF;
use XF\Entity\User;
use XF\Service\AbstractService;

class SubscriptionService extends AbstractService
{
    /**
     * @var User
     */
    protected $user;
    /**
     * @var string
     */
    protected $pushToken;

    /**
     * Subscription constructor.
     * @param \XF\App $app
     * @param User $user
     * @param string $pushToken
     */
    public function __construct(\XF\App $app, User $user, string $pushToken)
    {
        parent::__construct($app);

        $this->user = $user;
        $this->pushToken = $pushToken;
    }

    public function unsubscribe(): void
    {
        /** @var \Truonglv\Api\Entity\Subscription[] $subscriptions */
        $subscriptions = $this->finder('Truonglv\Api:Subscription')
            ->where('user_id', $this->user->user_id)
            ->where('device_token', $this->pushToken)
            ->fetch();
        foreach ($subscriptions as $subscription) {
            $subscription->delete();
        }
    }

    public function subscribe(array $extra): \Truonglv\Api\Entity\Subscription
    {
        /** @var \Truonglv\Api\Entity\Subscription|null $exists */
        $exists = $this->finder('Truonglv\Api:Subscription')
            ->where('user_id', $this->user->user_id)
            ->where('device_token', $this->pushToken)
            ->fetchOne();

        if ($exists !== null) {
            $subscription = $exists;
        } else {
            /** @var \Truonglv\Api\Entity\Subscription $subscription */
            $subscription = $this->em()->create('Truonglv\Api:Subscription');
            $subscription->user_id = $this->user->user_id;
            $subscription->username = $this->user->username;
            $subscription->device_token = $this->pushToken;
        }

        $subscription->subscribed_date = XF::$time;
        $subscription->bulkSet($extra);

        try {
            $subscription->save(false);
        } catch (\XF\Db\DuplicateKeyException $e) {
        }

        return $subscription;
    }
}
