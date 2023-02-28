<?php

namespace Truonglv\Api\Entity;

use XF;
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

    public function getEntityColumnLabel(string $columnName): ?string
    {
        switch ($columnName) {
            case 'expire_date':
                // @phpstan-ignore-next-line
                return XF::phrase('tapi_access_token_' . $columnName);
            case 'user_id':
                return XF::phrase('user_name');
        }

        return null;
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_tapi_access_token';
        $structure->shortName = 'Truonglv\Api:AccessToken';

        static::setupStructure($structure);
        $structure->columns['expire_date'] += [
            'inputFilter' => 'uint',
            'macroTemplate' => 'admin:tapi_access_token_macros'
        ];

        return $structure;
    }
}
