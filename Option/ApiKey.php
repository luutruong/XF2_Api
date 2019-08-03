<?php

namespace Truonglv\Api\Option;

use XF\Option\AbstractOption;
use XF\PrintableException;

class ApiKey extends AbstractOption
{
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

    public static function renderOption(\XF\Entity\Option $option, array $htmlParams)
    {
        return self::getTemplate('admin:tapi_option_template_apiKey', $option, $htmlParams, [
            'requiredScopes' => self::$requiredScopes,
            'availableApiKeys' => \XF::app()->finder('XF:ApiKey')->where('active', 1)->fetch()
        ]);
    }

    public static function verifyOption(array &$value)
    {
        if (!empty($value['apiKeyId'])) {
            /** @var \XF\Entity\ApiKey|null $apiKey */
            $apiKey = \XF::app()->em()->find('XF:ApiKey', $value['apiKeyId']);
            if (empty($apiKey)) {
                throw new PrintableException(\XF::phrase('tapi_requested_api_key_not_found'));
            }

            foreach (self::$requiredScopes as $scope) {
                if (!$apiKey->hasScope($scope)) {
                    throw new PrintableException(\XF::phrase('tapi_required_score_x', [
                        'scope' => $scope
                    ]));
                }
            }
        }

        return true;
    }
}
