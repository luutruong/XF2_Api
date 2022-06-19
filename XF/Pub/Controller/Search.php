<?php

namespace Truonglv\Api\XF\Pub\Controller;

use Truonglv\Api\Entity\SearchQuery;

class Search extends XFCP_Search
{
    /**
     * @param \XF\Search\Query\KeywordQuery $query
     * @param array $constraints
     * @param mixed $allowCached
     * @return \XF\Mvc\Reply\AbstractReply
     */
    protected function runSearch(\XF\Search\Query\KeywordQuery $query, array $constraints, $allowCached = true)
    {
        /** @var SearchQuery $searchQueryLogger */
        $searchQueryLogger = $this->em()->create('Truonglv\Api:SearchQuery');
        $searchQueryLogger->user_id = \XF::visitor()->user_id;
        $keywords = $query->getKeywords();
        if (strlen($keywords) > 0) {
            $searchQueryLogger->query_text = $query->getKeywords();
            $searchQueryLogger->save();
        }

        return parent::runSearch($query, $constraints, $allowCached);
    }
}
