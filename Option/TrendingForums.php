<?php

namespace Truonglv\Api\Option;

use XF;
use XF\Entity\Option;
use XF\Option\AbstractOption;
use XF\Repository\NodeRepository;

class TrendingForums extends AbstractOption
{
    public static function renderOption(Option $option, array $htmlParams): string
    {
        $choices = is_array($option->option_value) ? $option->option_value : [];

        /** @var NodeRepository $nodeRepo */
        $nodeRepo = XF::repository(NodeRepository::class);
        $forumChoices = $nodeRepo->getNodeOptionsData(true, 'Forum', 'option');

        return self::getTemplate(
            'admin:tapi_option_template_trending_forums',
            $option,
            $htmlParams,
            [
                'choices' => $choices,
                'forumChoices' => $forumChoices,
            ]
        );
    }

    public static function verifyOption(array &$value): bool
    {
        $output = [];

        foreach ($value as $forumId) {
            $forumId = (int) $forumId;
            if ($forumId <= 0) {
                continue;
            }

            $output[] = $forumId;
        }

        $value = array_values(array_unique($output));

        return true;
    }
}
