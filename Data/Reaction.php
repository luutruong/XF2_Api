<?php

namespace Truonglv\Api\Data;

use XF;
use function count;
use function intval;

class Reaction
{
    /**
     * @var array|null
     */
    protected $reactions;

    public function getReactions(): array
    {
        if ($this->reactions === null) {
            $reactions = [];
            $activeReactions = XF::finder('XF:Reaction')
                ->where('active', true)
                ->order('display_order')
                ->fetch();
            $enabledReactions = XF::app()->options()->tApi_reactions;

            if (count($enabledReactions) === 0) {
                $reactions[\Truonglv\Api\Option\Reaction::DEFAULT_REACTION_ID] = [
                    'imageUrl' => 'styles/default/Truonglv/Api/like.png',
                    'text' => $activeReactions[\Truonglv\Api\Option\Reaction::DEFAULT_REACTION_ID]->title,
                    'reactionId' => \Truonglv\Api\Option\Reaction::DEFAULT_REACTION_ID
                ];
            } else {
                foreach ($enabledReactions as $reaction) {
                    if (isset($activeReactions[$reaction['reactionId']])) {
                        $reactions[$reaction['reactionId']] = [
                            'imageUrl' => $reaction['imageUrl'],
                            'text' => $activeReactions[$reaction['reactionId']]->title,
                            'reactionId' => intval($reaction['reactionId'])
                        ];
                    }
                }
            }

            foreach ($reactions as &$reaction) {
                $reaction['imageUrl'] = XF::canonicalizeUrl($reaction['imageUrl']);
            }

            $this->reactions = $reactions;
        }

        return $this->reactions;
    }
}
