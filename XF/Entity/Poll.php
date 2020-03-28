<?php

namespace Truonglv\Api\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Poll extends XFCP_Poll
{
    protected function setupApiResultData(
        \XF\Api\Result\EntityResult $result,
        $verbosity = self::VERBOSITY_NORMAL,
        array $options = []
    ) {
        try {
            parent::setupApiResultData($result, $verbosity, $options);
        } catch (\LogicException $e) {
        }

        $result->is_closed = $this->isClosed();
        $result->can_vote = $this->canVote();
        $result->is_visitor_voted = $this->hasVoted();
    }

    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $apiColumns = [
            'poll_id',
            'question',
            'responses',
            'voter_count',
            'max_votes',
            'public_votes',
            'close_date',
        ];

        foreach ($apiColumns as $apiColumn) {
            $structure->columns[$apiColumn]['api'] = true;
        }

        return $structure;
    }
}
