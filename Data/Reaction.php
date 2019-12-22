<?php

namespace Truonglv\Api\Data;

class Reaction
{
    /**
     * @var array|null
     */
    protected $reactions;

    /**
     * @return array
     */
    public function getReactions()
    {
        if ($this->reactions === null) {
            $reactions = [];
            $activeReactions = \XF::finder('XF:Reaction')
                ->where('active', true)
                ->order('display_order')
                ->fetch();
            $enabledReactions = \XF::app()->options()->tApi_reactions;

            if (\count($enabledReactions) === 0) {
                $reactions[1] = [
                    'imageUrl' => 'styles/default/Truonglv/Api/like.png',
                    'text' => $activeReactions[1]->title,
                    'reactionId' => 1
                ];
            } else {
                foreach ($enabledReactions as $reaction) {
                    if (isset($activeReactions[$reaction['reactionId']])) {
                        $reactions[$reaction['reactionId']] = [
                            'imageUrl' => $reaction['imageUrl'],
                            'text' => $activeReactions[$reaction['reactionId']]->title,
                            'reactionId' => $reaction['reactionId']
                        ];
                    }
                }
            }

            foreach ($reactions as &$reaction) {
                $reaction['imageUrl'] = \XF::canonicalizeUrl($reaction['imageUrl']);
            }

            $this->reactions = $reactions;
        }

        return $this->reactions;
    }
}
