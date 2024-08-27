<?php

namespace Truonglv\Api\Option;

use XF;
use XF\Option\AbstractOption;

class ConnectedAccount extends AbstractOption
{
    public static function renderOption(\XF\Entity\Option $option, array $htmlParams): string
    {
        $connectedAccountRepo = XF::repository(XF\Repository\ConnectedAccountRepository::class);
        $choices = ['' => ''];
        $choices += $connectedAccountRepo->getConnectedAccountProviderTitlePairs();

        return static::getSelectRow($option, $htmlParams, $choices);
    }
}
