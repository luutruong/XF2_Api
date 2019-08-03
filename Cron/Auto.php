<?php

namespace Truonglv\Api\Cron;

class Auto
{
    public static function runHourly()
    {
        $logLength = \XF::options()->tApi_logLength;
        if ($logLength > 0) {
            \XF::db()->delete('xf_tapi_log', 'log_date <= ?', \XF::$time - $logLength * 86400);
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
