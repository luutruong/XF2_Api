<?php

namespace Truonglv\Api\XF\Api\Controller;

use XF;
use XF\Mvc\ParameterBag;
use XF\Entity\ThreadPrefix;
use XF\Mvc\Entity\AbstractCollection;

class ForumController extends XFCP_ForumController
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

        $prefixRepo = $this->repository(XF\Repository\ThreadPrefixRepository::class);
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
        if (XF::isApiCheckingPermissions() && !$forum->canWatch()) {
            return $this->noPermission();
        }

        $forumWatch = $this->repository(XF\Repository\ForumWatchRepository::class);

        $visitor = XF::visitor();
        /** @var \XF\Entity\ForumWatch|null $forumWatchEntity */
        $forumWatchEntity = $forum->Watch[$visitor->user_id];
        $newState = ($forumWatchEntity !== null) ? 'delete' : 'thread';

        $forumWatch->setWatchState($forum, $visitor, $newState, true, false);

        return $this->apiSuccess([
            'is_watched' => $newState !== 'delete'
        ]);
    }

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

        if ($this->request()->exists('with_first_post')) {
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

        if ($this->filter('with_first_post', 'bool') === true) {
            $result->includeRelation('FirstPost');
        }
    }
}
