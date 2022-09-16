<?php

namespace Truonglv\Api\XF\Entity;

use LogicException;
use XF\Mvc\Entity\Structure;

class Poll extends XFCP_Poll
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

        $result->is_closed = $this->isClosed();
        $result->can_vote = $this->canVote();
        $result->is_visitor_voted = $this->hasVoted();

        $responses = $this->responses;
        foreach ($responses as $responseId => &$responseRef) {
            $responseRef['is_visitor_voted'] = $this->hasVoted($responseId);
            $responseRef['vote_percentage'] = $this->getVotePercentage($responseRef['response_vote_count']);
        }

        $result->responses = $responses;
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
