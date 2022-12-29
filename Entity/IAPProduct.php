<?php

namespace Truonglv\Api\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $product_id
 * @property string $title
 * @property string $description
 * @property string $platform
 * @property string $store_product_id
 * @property int $user_upgrade_id
 * @property int $payment_profile_id
 * @property bool $active
 * @property int $display_order
 * @property bool $best_choice_offer
 *
 * RELATIONS
 * @property \XF\Entity\UserUpgrade $UserUpgrade
 * @property \XF\Entity\PaymentProfile $PaymentProfile
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
            'description' => ['type' => self::STR, 'default' => '', 'maxLength' => 255, 'api' => true],
            'platform' => ['type' => self::STR, 'allowedValues' => ['ios', 'android'], 'required' => true, 'api' => true],
            'store_product_id' => ['type' => self::STR, 'required' => true, 'maxLength' => 255, 'api' => true],
            'user_upgrade_id' => ['type' => self::UINT, 'required' => true, 'api' => true],
            'payment_profile_id' => ['type' => self::UINT, 'required' => true, 'api' => true],
            'active' => ['type' => self::BOOL, 'default' => true, 'api' => true],
            'display_order' => ['type' => self::UINT, 'default' => 1, 'api' => true],
            'best_choice_offer' => ['type' => self::BOOL, 'default' => false, 'api' => true],
        ];

        $structure->relations = [
            'UserUpgrade' => [
                'type' => self::TO_ONE,
                'entity' => 'XF:UserUpgrade',
                'conditions' => 'user_upgrade_id',
                'primary' => true,
            ],
            'PaymentProfile' => [
                'type' => self::TO_ONE,
                'entity' => 'XF:PaymentProfile',
                'conditions' => 'payment_profile_id',
                'primary' => true,
            ]
        ];

        return $structure;
    }

    protected function setupApiResultData(\XF\Api\Result\EntityResult $result, $verbosity = self::VERBOSITY_NORMAL, array $options = [])
    {
        // good.
    }
}
