<?php

namespace Truonglv\Api\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;

class Search extends AbstractController
{
    public function actionPost()
    {
        $this->assertRequiredApiInput(['keywords']);

        $visitor = \XF::visitor();
        if (\XF::isApiCheckingPermissions() && !$visitor->canSearch($error)) {
            return $this->message(\XF::phrase('no_results_found'));
        }

        $searchRequest = new \XF\Http\Request($this->app->inputFilterer(), [], [], []);

        $searcher = $this->app()->search();
        $query = $searcher->getQuery();

        $urlConstraints = [];

        $typeHandler = $searcher->handler('post');
        $query->forTypeHandler($typeHandler, $searchRequest, $urlConstraints);
        $query->withGroupedResults();

        $keywords = $this->filter('keywords', 'str');
        $query->withKeywords($keywords, false);
        $query->orderedBy('date');

        $constraints = [];

        /** @var \XF\Repository\Search $searchRepo */
        $searchRepo = $this->repository('XF:Search');
        $search = $searchRepo->runSearch($query, $constraints, true);

        if (!$search) {
            return $this->message(\XF::phrase('no_results_found'));
        }

        return $this->rerouteController(__CLASS__, 'get', [
            'search_id' => $search->search_id
        ]);
    }

    public function actionGet(ParameterBag $params)
    {
        $search = $this->assertSearchViewable($params->search_id);

        $page = $this->filterPage();
        $perPage = $this->options()->searchResultsPerPage;

        $searcher = $this->app()->search();
        $resultSet = $searcher->getResultSet($search->search_results);

        $resultSet->sliceResultsToPage($page, $perPage);

        if (!$resultSet->countResults()) {
            return $this->message(\XF::phrase('no_results_found'));
        }

        $results = [];
        /** @var Entity $entity */
        foreach ($resultSet->getResultsData() as $entity) {
            $results[] = $entity->toApiResult(Entity::VERBOSITY_VERBOSE);
        }

        $data = [
            'keywords' => $search->search_query,
            'search_id' => $search->search_id,
            'results' => $results,
            'pagination' => $this->getPaginationData($results, $page, $perPage, $search->result_count)
        ];

        return $this->apiResult($data);
    }

    /**
     * @param mixed $id
     * @return \XF\Entity\Search
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertSearchViewable($id)
    {
        /** @var \XF\Entity\Search $search */
        $search = $this->assertRecordExists('XF:Search', $id);
        if (($search->user_id > 0 && $search->user_id !== \XF::visitor()->user_id)
            || $search->search_type !== 'post'
        ) {
            throw $this->exception($this->notFound());
        }

        return $search;
    }
}
