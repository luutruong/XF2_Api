<?php

namespace Truonglv\Api\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int alert_id
 *
 * RELATIONS
 * @property \XF\Entity\UserAlert UserAlert
 */
class AlertQueue extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_tapi_alert_queue';
        $structure->primaryKey = 'alert_id';
        $structure->shortName = 'Truonglv\Api:AlertQueue';

        $structure->columns = [
            'alert_id' => ['type' => self::UINT, 'required' => true]
        ];

        $structure->relations = [
            'UserAlert' => [
                'type' => self::TO_ONE,
                'entity' => 'XF:UserAlert',
                'conditions' => 'alert_id',
                'primary' => true
            ]
        ];

        return $structure;
    }
}
