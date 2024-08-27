<?php

namespace Truonglv\Api\Api\ControllerPlugin;

use XF\Finder\ReactionContentFinder;
use XF\Mvc\Entity\Entity;
use XF\Api\ControllerPlugin\AbstractPlugin;

class Reaction extends AbstractPlugin
{
    /**
     * @param string $contentType
     * @param Entity $content
     * @return \XF\Api\Mvc\Reply\ApiResult
     */
    public function actionReactions($contentType, Entity $content)
    {
        $input = $this->filter([
            // filter specific reaction ID
           'reaction_id' => 'uint',
        ]);

        $finder = $this->finder(ReactionContentFinder::class);

        $finder->where('content_type', $contentType);
        $finder->where('content_id', $content->getEntityId());
        $finder->with('ReactionUser');
        $finder->setDefaultOrder('reaction_date', 'desc');

        $reactions = $this->app['reactions'];
        /** @var \Truonglv\Api\Data\Reaction $reactionData */
        $reactionData = $this->data('Truonglv\Api:Reaction');
        $ourReactions = $reactionData->getReactions();

        if ($input['reaction_id'] > 0) {
            if (isset($reactions[$input['reaction_id']])
                && isset($ourReactions[$input['reaction_id']])
                && $reactions[$input['reaction_id']]['active'] === true
            ) {
                $finder->where('reaction_id', $input['reaction_id']);
            } else {
                $finder->whereImpossible();
            }
        }

        $page = $this->filterPage();
        $perPage = $this->options()->tApi_recordsPerPage;

        $total = $finder->total();
        $entities = $finder->limitByPage($page, $perPage)->fetch();

        return $this->apiResult([
            'pagination' => $this->getPaginationData($entities, $page, $perPage, $total),
            'reactions' => $entities->toApiResults(Entity::VERBOSITY_VERBOSE),
        ]);
    }
}
