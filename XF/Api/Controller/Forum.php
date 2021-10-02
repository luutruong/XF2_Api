<?php

namespace Truonglv\Api\XF\Api\Controller;

use Truonglv\Api\App;
use XF\Mvc\ParameterBag;
use XF\Entity\ThreadPrefix;
use XF\Repository\ForumWatch;
use XF\Mvc\Entity\AbstractCollection;

class Forum extends XFCP_Forum
{
    public function actionGetPrefixes(ParameterBag $params)
    {
        $forum = $this->assertViewableForum($params->node_id);

        $prefixTree = [];
        $prefixes = [];

        foreach ($forum->getUsablePrefixes() as $index => $prefixGrouped) {
            /** @var ThreadPrefix $prefix */
            foreach ($prefixGrouped as $id => $prefix) {
                $prefixes[$id] = $prefix;
                $prefixTree[$index][] = $prefix->prefix_id;
            }
        }

        /** @var \XF\Repository\ThreadPrefix $prefixRepo */
        $prefixRepo = $this->repository('XF:ThreadPrefix');
        /** @var AbstractCollection $prefixGroups */
        $prefixGroups = $prefixRepo->findPrefixGroups(true);

        $data = [
            'prefix_groups' => $prefixGroups->count() > 1 ?  $prefixGroups->toApiResults() : [],
            'prefixes' => $this->em()->getBasicCollection($prefixes)->toApiResults(),
            'prefix_tree' => $prefixTree
        ];

        return $this->apiSuccess($data);
    }

    public function actionPostWatch(ParameterBag $params)
    {
        $forum = $this->assertViewableForum($params->node_id);
        if (\XF::isApiCheckingPermissions() && $forum->canWatch()) {
            return $this->noPermission();
        }

        /** @var ForumWatch $forumWatch */
        $forumWatch = $this->repository('XF:ForumWatch');

        $visitor = \XF::visitor();
        /** @var \XF\Entity\ForumWatch|null $forumWatchEntity */
        $forumWatchEntity = $forum->Watch[$visitor->user_id];
        $newState = ($forumWatchEntity !== null) ? 'delete' : 'watch_no_email';

        $forumWatch->setWatchState($forum, $visitor, $newState);

        return $this->apiSuccess([
            'is_watched' => $newState !== 'delete'
        ]);
    }

    /**
     * @param \XF\Entity\Forum $forum
     * @param mixed $page
     * @param mixed $perPage
     * @return array
     */
    protected function getThreadsInForumPaginated(\XF\Entity\Forum $forum, $page = 1, $perPage = null)
    {
        if (App::isRequestFromApp()) {
            $perPage = $this->options()->tApi_recordsPerPage;
        }

        return parent::getThreadsInForumPaginated($forum, $page, $perPage);
    }

    /**
     * @param \XF\Entity\Forum $forum
     * @param array $filters
     * @param mixed $sort
     * @return \XF\Finder\Thread
     */
    protected function setupThreadFinder(\XF\Entity\Forum $forum, &$filters = [], &$sort = null)
    {
        $notFound = false;
        if ($this->request()->exists('started_by')) {
            $startedBy = $this->filter('started_by', 'str');
            if (utf8_strlen($startedBy) > 0) {
                /** @var \XF\Entity\User|null $user */
                $user = $this->em()->findOne('XF:User', [
                    'username' => $startedBy
                ]);
                if ($user === null) {
                    $notFound = true;
                } else {
                    $this->request()->set('starter_id', $user->user_id);
                }
            }
        }

        $finder = parent::setupThreadFinder($forum, $filters, $sort);

        if (App::isRequestFromApp()) {
            $finder->with('FirstPost');
        }
        if ($notFound) {
            $finder->whereImpossible();
        }

        return $finder;
    }

    /**
     * @param \XF\Entity\Forum $forum
     * @param \XF\Api\Result\EntityResultInterface $result
     * @return void
     */
    protected function adjustThreadListApiResults(\XF\Entity\Forum $forum, \XF\Api\Result\EntityResultInterface $result)
    {
        parent::adjustThreadListApiResults($forum, $result);

        if (App::isRequestFromApp()) {
            $result->includeRelation('FirstPost');
        }
    }
}
