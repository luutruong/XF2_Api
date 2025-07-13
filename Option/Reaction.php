<?php

namespace Truonglv\Api\Option;

use XF;
use function trim;
use function count;
use XF\PrintableException;
use function array_replace;
use XF\Option\AbstractOption;
use XF\Mvc\Entity\AbstractCollection;

class Reaction extends AbstractOption
{
    const MAXIMUM_REACTIONS = 6;
    const DEFAULT_REACTION_ID = 1;

    /**
     * @param \XF\Entity\Option $option
     * @param array $htmlParams
     * @return string
     */
    public static function renderOption(\XF\Entity\Option $option, array $htmlParams)
    {
        $reactions = self::getReactions();

        return self::getTemplate('admin:tapi_option_template_reactions', $option, $htmlParams, [
            'reactions' => $reactions,
            'maxReactions' => self::MAXIMUM_REACTIONS - 1
        ]);
    }

    /**
     * @param array $values
     * @throws PrintableException
     * @return bool
     */
    public static function verifyOption(& $values)
    {
        $output = [];
        $reactions = self::getReactions();

        $defaultReactionFound = false;
        foreach ($values as $value) {
            if ($value['reactionId'] == self::DEFAULT_REACTION_ID) {
                $defaultReactionFound = true;

                break;
            }
        }

        if (!$defaultReactionFound) {
            /** @var \XF\Entity\Reaction $reactionEntity */
            $reactionEntity = $reactions[self::DEFAULT_REACTION_ID];

            throw new PrintableException(XF::phrase('tapi_reaction_x_are_required', [
                'title' => $reactionEntity->title
            ]));
        }

        foreach ($values as $index => $value) {
            $value = array_replace([
                'reactionId' => 0,
                'imageUrl' => ''
            ], $value);
            if (isset($reactions[$value['reactionId']])
                && trim($value['imageUrl']) !== ''
            ) {
                $output[] = $value;
            }
        }

        if (count($output) > self::MAXIMUM_REACTIONS) {
            throw new PrintableException('Too many reactions provided!');
        }

        $values = $output;

        return true;
    }

    protected static function getReactions(): AbstractCollection
    {
        return XF::finder(XF\Finder\ReactionFinder::class)
            ->where('active', true)
            ->order('display_order')
            ->fetch();
    }
}
