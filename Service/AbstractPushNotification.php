<?php

namespace Truonglv\Api\Service;

use XF\Entity\UserAlert;
use XF\Service\AbstractService;

abstract class AbstractPushNotification extends AbstractService
{
    /**
     * @var UserAlert
     */
    protected $alert;

    public function __construct(\XF\App $app, UserAlert $alert)
    {
        parent::__construct($app);

        $this->alert = $alert;

        $this->setupDefaults();
    }

    abstract public function send();

    /**
     * @return \XF\Mvc\Entity\Finder
     */
    protected function findSubscriptions()
    {
        return $this->app->finder('Truonglv\Api:Subscription')
            ->where('user_id', $this->alert->alerted_user_id);
    }

    protected function setupDefaults()
    {
    }
}
