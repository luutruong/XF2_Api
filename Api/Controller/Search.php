<?php

namespace Truonglv\Api\Api\Controller;

use Truonglv\Api\Repository\SearchQueryRepository;
use XF;
use function max;
use function md5;
use function count;
use XF\Entity\Post;
use function strlen;
use XF\Repository\Tag;
use XF\Mvc\ParameterBag;
use function utf8_strlen;
use XF\Mvc\Entity\Entity;
use function utf8_strtolower;
use Truonglv\Api\Entity\SearchQuery;
use XF\Api\Controller\AbstractController;

class Search extends AbstractController
{
    const SEARCH_TYPE_THREAD = 'thread';
    const SEARCH_TYPE_POST = 'post';
    const SEARCH_TYPE_USER = 'user';
    const SEARCH_TYPE_RESOURCE = 'resource';

    public function actionPost()
    {
        $this->assertRequiredApiInput(['keywords']);

        $visitor = XF::visitor();
        if (XF::isApiCheckingPermissions() && !$visitor->canSearch($error)) {
            return $this->message(XF::phrase('no_results_found'));
        }

        $searchType = $this->filter('search_type', 'str');
        if (!in_array($searchType, $this->getAllowedSearchTypes(), true)) {
            $searchType = '';
        }

        $searcher = $this->app()->search();

        $searchOrder = $this->filter('search_order', 'str');
        $allowedOrders = ['date'];
        if ($searcher->isRelevanceSupported()) {
            $allowedOrders[] = 'relevance';
        }
        if (!in_array($searchOrder, $allowedOrders, true)) {
            $searchOrder = end($allowedOrders);
        }

        $keywords = $this->app()->stringFormatter()->censorText($this->filter('keywords', 'str'), '');
        if ($searchType === self::SEARCH_TYPE_USER) {
            $this->request()->set('name', $keywords);

            return $this->rerouteController(__CLASS__, 'user');
        }

        $searchRequest = new \XF\Http\Request($this->app->inputFilterer(), [], [], []);
        $query = $searcher->getQuery();
        /** @var \XF\Entity\Tag|null $tag */
        $tag = null;

        if (strpos($keywords, 'tag:') === 0) {
            $tagName = substr($keywords, 4);
            $tagRepo = $this->repository(XF\Repository\TagRepository::class);
            if (!$tagRepo->isValidTag($tagName)) {
                return $this->message(XF::phrase('no_results_found'));
            }

            $tags = $tagRepo->getTags([$tagName]);
            $foundTag = reset($tags);
            if (!$foundTag instanceof \XF\Entity\Tag) {
                return $this->message(XF::phrase('no_results_found'));
            }

            $tag = $foundTag;
        } elseif (strlen($keywords) <= $this->options()->searchMinWordLength) {
            return $this->message(XF::phrase('search_could_not_be_completed_because_search_keywords_were_too'));
        }

        $urlConstraints = [];
        if ($searchType !== '') {
            $typeHandler = $searcher->handler($searchType);
            $query->forTypeHandler($typeHandler, $searchRequest, $urlConstraints);
        }

        $query->withGroupedResults();
        /** @var SearchQuery $searchQueryLogger */
        $searchQueryLogger = $this->em()->create('Truonglv\Api:SearchQuery');
        $searchQueryLogger->user_id = XF::visitor()->user_id;

        if ($tag !== null) {
            $query->withTags($tag->tag_id);
            $searchQueryLogger->query_text = 'tag:' . $tag->tag;
        } else {
            $query->withKeywords($keywords);
            $searchQueryLogger->query_text = $keywords;
        }

        $searchQueryLogger->save();
        $query->orderedBy($searchOrder);

        $constraints = [];

        $searchRepo = $this->repository(XF\Repository\SearchRepository::class);
        $search = $searchRepo->runSearch($query, $constraints, true);

        if (!$search) {
            return $this->message(XF::phrase('no_results_found'));
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
            return $this->message(XF::phrase('no_results_found'));
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

                $result = $entity->toApiResult(Entity::VERBOSITY_VERBOSE, $this->getApiResultOptions($entity));
                $result->includeExtra('content_type', $entity->getEntityContentType());
                $result->includeExtra('content_id', $entity->getEntityId());

                $results[] = $result;
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

    public function actionGetTrendingQueries()
    {
        $searchQueryRepo = $this->repository(SearchQueryRepository::class);

        return $this->apiResult([
            'queries' => $searchQueryRepo->getTrendingQueries(),
        ]);
    }

    public function actionUser()
    {
        $name = $this->filter('name', 'str');

        if (utf8_strlen($name) <= 2) {
            return $this->message(XF::phrase('no_results_found'));
        }

        $queryHash = md5(
            $this->app()->config('globalSalt')
            . __METHOD__
            . self::SEARCH_TYPE_USER
            . utf8_strtolower($name)
        );

        /** @var \XF\Entity\Search|null $existingSearch */
        $existingSearch = $this->finder(XF\Finder\SearchFinder::class)
            ->where('user_id', 0)
            ->where('query_hash', $queryHash)
            ->where('search_type', self::SEARCH_TYPE_USER)
            ->where('search_date', '>=', XF::$time - 3600)
            ->order('search_date', 'desc')
            ->fetchOne();
        if ($existingSearch !== null) {
            return $this->rerouteController(__CLASS__, 'get', [
                'search_id' => $existingSearch->search_id
            ]);
        }

        $finder = $this->finder(XF\Finder\UserFinder::class);
        $finder->where('username', 'LIKE', $finder->escapeLike($name, '?%'));
        $finder->order('username');

        $results = $finder->limit(max(XF::options()->maximumSearchResults, 20))
            ->fetchColumns('user_id');
        $searchResults = [];

        foreach ($results as $result) {
            $searchResults[] = ['user', $result['user_id']];
        }

        if (count($searchResults) === 0) {
            return $this->message(XF::phrase('no_results_found'));
        }

        /** @var \XF\Entity\Search $search */
        $search = $this->em()->create('XF:Search');

        $search->user_id = 0;
        $search->result_count = count($searchResults);
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

    protected function getApiResultOptions(Entity $entity): array
    {
        if ($entity instanceof Post) {
            return [
                'with_thread' => true,
            ];
        }

        return [];
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
        if (($search->user_id > 0 && $search->user_id !== XF::visitor()->user_id)
            || (
                $search->search_type !== ''
                && !in_array($search->search_type, $this->getAllowedSearchTypes(), true)
            )
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
        $searchTypes = [self::SEARCH_TYPE_THREAD, self::SEARCH_TYPE_POST, self::SEARCH_TYPE_USER];
        if (\Truonglv\Api\App::canViewResources()) {
            $searchTypes[] = self::SEARCH_TYPE_RESOURCE;
        }

        return $searchTypes;
    }
}
