<?php

namespace Truonglv\Api\Entity;

use function time;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

trait TokenTrait
{
    public function isExpired(): bool
    {
        return $this->expire_date > 0 && $this->expire_date <= time();
    }

    public static function setupStructure(Structure $structure): void
    {
        $structure->primaryKey = 'token';
        $structure->columns = [
            'token' => ['type' => Entity::STR, 'required' => true, 'maxLength' => 32],
            'user_id' => ['type' => Entity::UINT, 'required' => true],
            'expire_date' => ['type' => Entity::UINT, 'default' => 0],
            'created_date' => ['type' => Entity::UINT, 'default' => time()]
        ];

        $structure->relations = [
            'User' => [
                'type' => Entity::TO_ONE,
                'entity' => 'XF:User',
                'conditions' => 'user_id',
                'primary' => true
            ]
        ];
    }
}
