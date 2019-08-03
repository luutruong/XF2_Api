<?php

namespace Truonglv\Api\Cron;

use XF\Timer;

class Auto
{
    public static function runHourly()
    {
        $logLength = \XF::options()->tApi_logLength;
        if ($logLength > 0) {
            \XF::db()->delete('xf_tapi_log', 'log_date <= ?', \XF::$time - $logLength * 86400);
        }

        $subscriptionInactiveLength = \XF::options()->tApi_inactiveDeviceLength;
        if ($subscriptionInactiveLength > 0) {
            $timer = new Timer(3);
            $entities = \XF::finder('Truonglv\Api:Subscription')
                ->where('subscribed_date', '<=', \XF::$time - $subscriptionInactiveLength * 86400)
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

    public static function sendNotifications()
    {
        \XF::app()
            ->jobManager()
            ->enqueueUnique(
                'tapi_pushNotifications',
                'Truonglv\Api:PushNotification'
            );
    }
}
