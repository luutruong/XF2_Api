<?php

namespace Truonglv\Api\Api\Controller;

use XF\Repository\Tag;
use XF\Mvc\ParameterBag;
use XF\Mvc\Entity\Entity;
use XF\Api\Controller\AbstractController;

class Search extends AbstractController
{
    const SEARCH_TYPE_THREAD = 'thread';
    const SEARCH_TYPE_POST = 'post';
    const SEARCH_TYPE_USER = 'user';

    public function actionPost()
    {
        $this->assertRequiredApiInput(['keywords']);

        $visitor = \XF::visitor();
        if (\XF::isApiCheckingPermissions() && !$visitor->canSearch($error)) {
            return $this->message(\XF::phrase('no_results_found'));
        }

        $searchType = $this->filter('search_type', 'str');
        if (!in_array($searchType, $this->getAllowedSearchTypes(), true)) {
            $searchType = '';
        }

        $searchOrder = $this->filter('search_order', 'str');
        $allowedOrders = ['date', 'relevance'];
        if (!in_array($searchOrder, $allowedOrders, true)) {
            $searchOrder = 'date';
        }

        $keywords = $this->app()->stringFormatter()->censorText($this->filter('keywords', 'str'), '');
        if ($searchType === self::SEARCH_TYPE_USER) {
            $this->request()->set('name', $keywords);

            return $this->rerouteController(__CLASS__, 'user');
        }

        $searchRequest = new \XF\Http\Request($this->app->inputFilterer(), [], [], []);

        $searcher = $this->app()->search();
        $query = $searcher->getQuery();
        /** @var \XF\Entity\Tag|null $tag */
        $tag = null;

        if (strpos($keywords, 'tag:') === 0) {
            $tagName = substr($keywords, 4);
            /** @var Tag $tagRepo */
            $tagRepo = $this->repository('XF:Tag');
            if (!$tagRepo->isValidTag($tagName)) {
                return $this->message(\XF::phrase('no_results_found'));
            }

            $tags = $tagRepo->getTags([$tagName]);
            $foundTag = reset($tags);
            if (!$foundTag instanceof \XF\Entity\Tag) {
                return $this->message(\XF::phrase('no_results_found'));
            }

            $tag = $foundTag;
        } elseif (\strlen($keywords) <= $this->options()->searchMinWordLength) {
            return $this->message(\XF::phrase('no_results_found'));
        }

        $urlConstraints = [];
        if ($searchType !== '') {
            $typeHandler = $searcher->handler($searchType);
            $query->forTypeHandler($typeHandler, $searchRequest, $urlConstraints);
        }

        $query->withGroupedResults();

        if ($tag !== null) {
            $query->withTags($tag->tag_id);
        } else {
            $query->withKeywords($keywords, $searchType !== 'post');
        }
        $query->orderedBy($searchOrder);

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
        $perPage = $this->options()->tApi_recordsPerPage;

        $searcher = $this->app()->search();
        $resultSet = $searcher->getResultSet($search->search_results);

        $resultSet->sliceResultsToPage($page, $perPage, $search->search_type !== self::SEARCH_TYPE_USER);
        if (!$resultSet->countResults()) {
            return $this->message(\XF::phrase('no_results_found'));
        }

        $results = [];
        if ($search->search_type === self::SEARCH_TYPE_USER) {
            $userIds = [];
            foreach ($resultSet->getResults() as $result) {
                $userIds[] = $result[1];
            }

            $results = $this->em()
                ->findByIds('XF:User', $userIds, ['Profile', 'Privacy', 'Option'])
                ->sortByList($userIds);
        } else {
            /** @var Entity $entity */
            foreach ($resultSet->getResultsData() as $entity) {
                if (!in_array($entity->getEntityContentType(), $this->getAllowedSearchTypes(), true)) {
                    continue;
                }

                $results[] = $entity->toApiResult();
            }
        }

        $data = [
            'keywords' => $search->search_query,
            'search_id' => $search->search_id,
            'results' => $results,
            'pagination' => $this->getPaginationData($results, $page, $perPage, $search->result_count)
        ];

        return $this->apiResult($data);
    }

    public function actionUser()
    {
        $name = $this->filter('name', 'str');

        if (\utf8_strlen($name) <= 2) {
            return $this->message(\XF::phrase('no_results_found'));
        }

        $queryHash = \md5(
            $this->app()->config('globalSalt')
            . __METHOD__
            . self::SEARCH_TYPE_USER
            . \utf8_strtolower($name)
        );

        /** @var \XF\Entity\Search|null $existingSearch */
        $existingSearch = $this->finder('XF:Search')
            ->where('user_id', 0)
            ->where('query_hash', $queryHash)
            ->where('search_type', self::SEARCH_TYPE_USER)
            ->where('search_date', '>=', \XF::$time - 3600)
            ->order('search_date', 'desc')
            ->fetchOne();
        if ($existingSearch !== null) {
            return $this->rerouteController(__CLASS__, 'get', [
                'search_id' => $existingSearch->search_id
            ]);
        }

        $finder = $this->finder('XF:User');
        $finder->where('username', 'LIKE', $finder->escapeLike($name, '?%'));
        $finder->order('username');

        $results = $finder->limit(\max(\XF::options()->maximumSearchResults, 20))
            ->fetchColumns('user_id');
        $searchResults = [];

        foreach ($results as $result) {
            $searchResults[] = ['user', $result['user_id']];
        }

        if (\count($searchResults) === 0) {
            return $this->message(\XF::phrase('no_results_found'));
        }

        /** @var \XF\Entity\Search $search */
        $search = $this->em()->create('XF:Search');

        $search->user_id = 0;
        $search->result_count = \count($searchResults);
        $search->search_results = $searchResults;
        $search->search_type = self::SEARCH_TYPE_USER;
        $search->search_constraints = [];
        $search->search_order = 'date';
        $search->query_hash = $queryHash;
        $search->search_query = $name;

        $search->save();

        return $this->rerouteController(__CLASS__, 'get', [
            'search_id' => $search->search_id
        ]);
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
            || !in_array($search->search_type, $this->getAllowedSearchTypes(), true)
        ) {
            throw $this->exception($this->notFound());
        }

        return $search;
    }

    /**
     * @return string[]
     */
    protected function getAllowedSearchTypes()
    {
        return [self::SEARCH_TYPE_THREAD, self::SEARCH_TYPE_POST, self::SEARCH_TYPE_USER];
    }
}
