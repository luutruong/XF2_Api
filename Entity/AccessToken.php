<?php

namespace Truonglv\Api\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property string token
 * @property int user_id
 * @property int expire_date
 * @property int created_date
 *
 * RELATIONS
 * @property \XF\Entity\User User
 */
class AccessToken extends Entity
{
    /**
     * @return void
     */
    public function renewExpires()
    {
        $ttl = $this->app()->options()->tApi_accessTokenTtl;
        if ($ttl <= 0) {
            $this->expire_date = 0;
        } else {
            $this->expire_date = \XF::$time + $ttl * 60;
        }
    }

    public function isExpired(): bool
    {
        return $this->expire_date <= \XF::$time;
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_tapi_access_token';
        $structure->primaryKey = 'token';
        $structure->shortName = 'Truonglv\Api:AccessToken';

        $structure->columns = [
            'token' => ['type' => self::STR, 'required' => true, 'maxLength' => 32],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'expire_date' => ['type' => self::UINT, 'default' => 0],
            'created_date' => ['type' => self::UINT, 'default' => \XF::$time]
        ];

        $structure->relations = [
            'User' => [
                'type' => self::TO_ONE,
                'entity' => 'XF:User',
                'conditions' => 'user_id',
                'primary' => true
            ]
        ];

        return $structure;
    }
}
