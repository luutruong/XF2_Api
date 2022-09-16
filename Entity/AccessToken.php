<?php

namespace Truonglv\Api\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property string $token
 * @property int $user_id
 * @property int $expire_date
 * @property int $created_date
 *
 * RELATIONS
 * @property \XF\Entity\User $User
 */
class AccessToken extends Entity
{
    use TokenTrait;

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_tapi_access_token';
        $structure->shortName = 'Truonglv\Api:AccessToken';

        static::setupStructure($structure);

        return $structure;
    }
}
