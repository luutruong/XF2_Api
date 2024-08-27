<?php

namespace Truonglv\Api\Cron;

use XF;
use XF\Timer;
use Truonglv\Api\Repository\TokenRepository;

class Auto
{
    /**
     * @return void
     */
    public static function runHourly()
    {
        $logLength = XF::options()->tApi_logLength;
        XF::db()->delete('xf_tapi_log', 'log_date <= ?', XF::$time - $logLength * 86400);

        $tokenRepo = XF::repository(TokenRepository::class);
        $tokenRepo->pruneTokens();

        $subscriptionInactiveLength = XF::options()->tApi_inactiveDeviceLength;
        if ($subscriptionInactiveLength > 0) {
            $timer = new Timer(3);
            $entities = XF::finder('Truonglv\Api:Subscription')
                ->where('subscribed_date', '<=', XF::$time - $subscriptionInactiveLength * 86400)
                ->order('subscribed_date')
                ->limit(20)
                ->fetch();

            foreach ($entities as $entity) {
                $entity->delete(false);

                if ($timer->limitExceeded()) {
                    break;
                }
            }
        }
    }

    /**
     * @return void
     */
    public static function runMinutely()
    {
        XF::app()
            ->jobManager()
            ->enqueueLater(
                'tapi_alertQueue',
                XF::$time,
                'Truonglv\Api:AlertQueue'
            );
    }
}
