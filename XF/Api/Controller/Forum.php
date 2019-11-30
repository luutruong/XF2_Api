<?php

namespace Truonglv\Api\XF\Api\Controller;

use Truonglv\Api\App;
use XF\Entity\ThreadPrefix;
use XF\Mvc\ParameterBag;

class Forum extends XFCP_Forum
{
    public function actionGetThreads(ParameterBag $params)
    {
        $this->app()
            ->request()
            ->set(App::PARAM_KEY_INCLUDE_MESSAGE_HTML, true);

        return parent::actionGetThreads($params);
    }

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
        $prefixGroups = $prefixRepo->findPrefixGroups(true);

        $data = [
            'prefix_groups' => $prefixGroups->count() > 1 ?  $prefixGroups->toApiResults() : [],
            'prefixes' => $this->em()->getBasicCollection($prefixes)->toApiResults(),
            'prefix_tree' => $prefixTree
        ];

        return $this->apiSuccess($data);
    }

    /**
     * @param \XF\Entity\Forum $forum
     * @param array $filters
     * @param mixed $sort
     * @return \XF\Finder\Thread
     */
    protected function setupThreadFinder(\XF\Entity\Forum $forum, &$filters = [], &$sort = null)
    {
        $finder = parent::setupThreadFinder($forum, $filters, $sort);

        if (App::isRequestFromApp()) {
            $finder->with('FirstPost');
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
