<?php

namespace Truonglv\Api\Option;

use XF\PrintableException;
use XF\Option\AbstractOption;

class Reaction extends AbstractOption
{
    const MAXIMUM_REACTIONS = 6;

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
    public static function verifyOption(&$values)
    {
        $output = [];
        $reactions = self::getReactions();

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

    /**
     * @return \XF\Mvc\Entity\ArrayCollection
     */
    protected static function getReactions()
    {
        return \XF::finder('XF:Reaction')
            ->where('active', true)
            ->order('display_order')
            ->fetch();
    }
}
