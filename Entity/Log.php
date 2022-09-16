<?php

namespace Truonglv\Api\Entity;

use XF;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $log_id
 * @property int $user_id
 * @property string $app_version
 * @property string $end_point
 * @property string $method
 * @property array $payload
 * @property int $response_code
 * @property string $response
 * @property int $log_date
 *
 * RELATIONS
 * @property \XF\Entity\User $User
 */
class Log extends Entity
{
    /**
     * @return string
     */
    public function getEntityLabel()
    {
        return $this->method . ' - ' . $this->end_point;
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_tapi_log';
        $structure->primaryKey = 'log_id';
        $structure->shortName = 'Truonglv\Api:Log';

        $structure->columns = [
            'log_id' => ['type' => self::UINT, 'nullable' => true, 'autoIncrement' => true],
            'user_id' => ['type' => self::UINT, 'default' => 0],
            'app_version' => ['type' => self::STR, 'default' => '', 'maxLength' => 50],
            'end_point' => ['type' => self::STR, 'required' => true],
            'method' => ['type' => self::STR, 'required' => true, 'maxLength' => 12],
            'payload' => ['type' => self::JSON_ARRAY, 'default' => []],
            'response_code' => ['type' => self::UINT, 'default' => 0],
            'response' => ['type' => self::STR, 'default' => ''],
            'log_date' => ['type' => self::UINT, 'default' => XF::$time]
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
