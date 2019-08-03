<?php

namespace Truonglv\Api\Util;

use Truonglv\Api\Entity\AccessToken;
use XF\Util\Random;

class Token
{
    /**
     * @param int $userId
     * @param int $ttl Minutes
     * @param int $limit
     * @return string
     * @throws \ErrorException
     * @throws \XF\PrintableException
     */
    public static function generateAccessToken($userId, $ttl = 60, $limit = 10)
    {
        $retried = 0;
        $app = \XF::app();

        while ($retried < $limit) {
            $retried++;

            $token = Random::getRandomString(32);
            $exists = $app->finder('Truonglv\Api:AccessToken')
                ->whereId($token)
                ->fetchOne();

            if ($exists === null) {
                /** @var AccessToken $entity */
                $entity = $app->em()->create('Truonglv\Api:AccessToken');

                $entity->token = $token;
                $entity->user_id = $userId;
                $entity->expire_date = $ttl > 0 ? (\XF::$time + $ttl * 60) : 0;

                $entity->save();

                return $token;
            }

            usleep($retried * 50);
        }

        throw new \RuntimeException('Too many retries to create accessToken');
    }
}
