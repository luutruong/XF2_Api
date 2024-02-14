<?php

namespace Truonglv\Api\Repository;

use XF;
use function md5;
use function time;
use XF\Util\Random;
use function usleep;
use function sprintf;
use RuntimeException;
use function microtime;
use XF\Mvc\Entity\Repository;

class Token extends Repository
{
    public function deleteUserTokens(int $userId): void
    {
        $this->db()->delete('xf_tapi_access_token', 'user_id = ?', $userId);
    }

    public function pruneTokens(): void
    {
        $tables = ['xf_tapi_access_token', 'xf_tapi_refresh_token'];
        foreach ($tables as $tableName) {
            $this->db()->delete(
                $tableName,
                'expire_date <= ?',
                [time()]
            );
        }
    }

    public function createAccessToken(int $userId, ?int $seconds = null, int $limit = 5): string
    {
        if ($seconds === null) {
            $seconds = (int) $this->app()->options()->tApi_accessTokenTtl;
        }

        return $this->createToken(
            'Truonglv\Api:AccessToken',
            $userId,
            $seconds,
            $limit
        );
    }

    public function createRefreshToken(int $userId, int $seconds, int $limit = 5): string
    {
        return $this->createToken(
            'Truonglv\Api:RefreshToken',
            $userId,
            $seconds,
            $limit
        );
    }

    protected function createToken(
        string $entityClassName,
        int $userId,
        int $seconds,
        int $limit
    ): string {
        $retried = 0;
        $app = XF::app();
        $salt = md5(XF::config('globalSalt'), true);

        while ($retried < $limit) {
            $retried++;

            $token = md5(sprintf(
                '%s%f%d%s',
                Random::getRandomBytes(32),
                microtime(true),
                $userId,
                $salt
            ));
            $exists = $app->finder($entityClassName)
                ->whereId($token)
                ->fetchOne();

            if ($exists === null) {
                $entity = $app->em()->create($entityClassName);

                $entity->set('token', $token);
                $entity->set('user_id', $userId);
                $entity->set('expire_date', XF::$time + $seconds);

                $entity->save();

                return $token;
            }

            usleep($retried * 50);
        }

        throw new RuntimeException('Too many retries to create Token');
    }
}
