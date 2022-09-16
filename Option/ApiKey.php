<?php

namespace Truonglv\Api\Option;

use XF;
use XF\PrintableException;
use XF\Option\AbstractOption;

class ApiKey extends AbstractOption
{
    /**
     * @var array
     */
    public static $requiredScopes = [
        'attachment:delete',
        'attachment:read',
        'attachment:write',

        'conversation:read',
        'conversation:write',

        'node:read',

        'profile_post:read',
        'profile_post:write',

        'thread:read',
        'thread:write',

        'user:read',
        'user:write'
    ];

    /**
     * @param \XF\Entity\Option $option
     * @param array $htmlParams
     * @return string
     */
    public static function renderOption(\XF\Entity\Option $option, array $htmlParams)
    {
        return self::getTemplate('admin:tapi_option_template_apiKey', $option, $htmlParams, [
            'requiredScopes' => self::$requiredScopes,
            'availableApiKeys' => XF::app()->finder('XF:ApiKey')->where('active', 1)->fetch()
        ]);
    }

    /**
     * @param array $value
     * @return bool
     * @throws PrintableException
     */
    public static function verifyOption(array &$value)
    {
        if (isset($value['apiKeyId'])) {
            /** @var \XF\Entity\ApiKey|null $apiKey */
            $apiKey = XF::app()->em()->find('XF:ApiKey', $value['apiKeyId']);
            if ($apiKey === null) {
                throw new PrintableException(XF::phrase('tapi_requested_api_key_not_found'));
            }

            foreach (self::$requiredScopes as $scope) {
                if (!$apiKey->hasScope($scope)) {
                    throw new PrintableException(XF::phrase('tapi_required_score_x', [
                        'scope' => $scope
                    ]));
                }
            }
        }

        return true;
    }
}
