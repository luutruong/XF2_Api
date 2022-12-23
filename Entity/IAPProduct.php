<?php

namespace Truonglv\Api\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $product_id
 * @property string $title
 * @property string $platform
 * @property string $store_product_id
 * @property int $user_upgrade_id
 * @property bool $active
 * @property int $display_order
 *
 * RELATIONS
 * @property \XF\Mvc\Entity\AbstractCollection|\XF\Entity\UserUpgrade[] $UserUpgrade
 */
class IAPProduct extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_tapi_iap_product';
        $structure->primaryKey = 'product_id';
        $structure->shortName = 'Truonglv\Api:IAPProduct';

        $structure->columns = [
            'product_id' => ['type' => self::UINT, 'nullable' => true, 'autoIncrement' => true, 'api' => true],
            'title' => ['type' => self::STR, 'required' => true, 'maxLength' => 100, 'api' => true],
            'platform' => ['type' => self::STR, 'allowedValues' => ['ios', 'android'], 'required' => true, 'api' => true],
            'store_product_id' => ['type' => self::STR, 'required' => true, 'maxLength' => 255, 'api' => true],
            'user_upgrade_id' => ['type' => self::UINT, 'required' => true, 'api' => true],
            'active' => ['type' => self::BOOL, 'default' => true, 'api' => true],
            'display_order' => ['type' => self::UINT, 'default' => 1, 'api' => true],
        ];

        $structure->relations = [
            'UserUpgrade' => [
                'type' => self::UINT,
                'entity' => 'XF:UserUpgrade',
                'conditions' => 'user_upgrade_id',
                'primary' => true,
            ],
        ];

        return $structure;
    }

    protected function setupApiResultData(\XF\Api\Result\EntityResult $result, $verbosity = self::VERBOSITY_NORMAL, array $options = [])
    {
        // good.
    }
}
