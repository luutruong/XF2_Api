<?php

namespace Truonglv\Api\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null subscription_id
 * @property int user_id
 * @property string username
 * @property string app_version
 * @property string device_token
 * @property bool is_device_test
 * @property string provider
 * @property string provider_key
 * @property int subscribed_date
 *
 * RELATIONS
 * @property \XF\Entity\User User
 */
class Subscription extends Entity
{
    /**
     * @return string
     */
    public function getEntityLabel()
    {
        return $this->User !== null ? $this->User->username : $this->username;
    }

    /**
     * @param \XF\Api\Result\EntityResult $result
     * @param mixed $verbosity
     * @param array $options
     * @return void
     */
    protected function setupApiResultData(
        \XF\Api\Result\EntityResult $result,
        $verbosity = self::VERBOSITY_NORMAL,
        array $options = []
    ) {
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_tapi_subscription';
        $structure->primaryKey = 'subscription_id';
        $structure->shortName = 'Truonglv\Api:Subscription';

        $structure->columns = [
            'subscription_id' => ['type' => self::UINT, 'nullable' => true, 'autoIncrement' => true, 'api' => true],
            'user_id' => ['type' => self::UINT, 'required' => true, 'api' => true],
            'username' => ['type' => self::STR, 'required' => true, 'maxLength' => 50, 'api' => true],
            'app_version' => ['type' => self::STR, 'maxLength' => 50, 'default' => '', 'api' => true],
            'device_token' => ['type' => self::STR, 'required' => true, 'maxLength' => 200, 'api' => true],
            'is_device_test' => ['type' => self::BOOL, 'default' => false, 'api' => true],
            'provider' => [
                'type' => self::STR,
                'required' => true,
                'api' => true
            ],
            'provider_key' => ['type' => self::STR, 'maxLength' => 200, 'default' => '', 'api' => true],
            'subscribed_date' => ['type' => self::UINT, 'default' => \XF::$time, 'api' => true]
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

    protected function _postDelete()
    {
        if ($this->provider === 'one_signal') {
            // TODO: Remove support OneSignal
            $this->app()
                ->jobManager()
                ->enqueueUnique(
                    'tapi_unsubscribe' . $this->subscription_id,
                    'Truonglv\Api:Unsubscribe',
                    [
                        'provider' => $this->provider,
                        'provider_key' => $this->provider_key,
                        'device_token' => $this->device_token
                    ]
                );
        }
    }
}
