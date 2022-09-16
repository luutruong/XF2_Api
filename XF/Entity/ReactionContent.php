<?php

namespace Truonglv\Api\XF\Entity;

use LogicException;
use XF\Mvc\Entity\Structure;

class ReactionContent extends XFCP_ReactionContent
{
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
        try {
            parent::setupApiResultData($result, $verbosity, $options);
        } catch (LogicException $e) {
        }

        if ($verbosity >= self::VERBOSITY_VERBOSE) {
            $result->includeRelation('ReactionUser');
        }
    }

    public static function getStructure(Structure $structure)
    {
        $structure =  parent::getStructure($structure);

        $apiColumns = [
            'reaction_content_id',
            'reaction_id',
            'content_type',
            'content_id',
            'reaction_user_id',
            'reaction_date'
        ];
        foreach ($apiColumns as $apiColumn) {
            $structure->columns[$apiColumn]['api'] = true;
        }

        return $structure;
    }
}
