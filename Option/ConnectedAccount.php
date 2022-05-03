<?php

namespace Truonglv\Api\Option;

use XF\Option\AbstractOption;

class ConnectedAccount extends AbstractOption
{
    public static function renderOption(\XF\Entity\Option $option, array $htmlParams): string
    {
        /** @var \XF\Repository\ConnectedAccount $connectedAccountRepo */
        $connectedAccountRepo = \XF::repository('XF:ConnectedAccount');
        $choices = ['' => ''];
        $choices += $connectedAccountRepo->getConnectedAccountProviderTitlePairs();

        return static::getSelectRow($option, $htmlParams, $choices);
    }
}
