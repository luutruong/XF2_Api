<?php

namespace Truonglv\Api\Service;

use XF\Entity\UserAlert;
use Truonglv\Api\Entity\Log;
use XF\Service\AbstractService;

abstract class AbstractPushNotification extends AbstractService
{
    public function __construct(\XF\App $app)
    {
        parent::__construct($app);

        $this->setupDefaults();
    }

    abstract public function sendNotification(UserAlert $alert);

    abstract public function unsubscribe($externalId, $pushToken);

    /**
     * @return \XF\Mvc\Entity\Finder
     */
    protected function findSubscriptions()
    {
        return $this->app->finder('Truonglv\Api:Subscription');
    }

    protected function setupDefaults()
    {
    }

    protected function logRequest($method, $endPoint, array $payload, $responseCode, $response, array $extra = [])
    {
        $extra = array_replace([
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
}
