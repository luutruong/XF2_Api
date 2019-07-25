<?php

namespace Truonglv\Api\Api\Controller;

use XF\Finder\Thread;
use XF\Mvc\Entity\Entity;
use XF\Api\Controller\AbstractController;

class App extends AbstractController
{
    public function actionGetNewsFeeds()
    {
        $cache = $this->app()->cache();
        /** @var Thread $finder */
        $finder = $this->finder('XF:Thread');

        $page = $this->filterPage();
        $perPage = $this->options()->discussionsPerPage;

        if ($cache) {
            $threadIds = $cache->fetch('tApi_NewsFeeds_threadIds');
            if (!$threadIds) {
                $this->applyNewsFeedsFilter($finder);
                $finder->limit($this->options()->maximumSearchResults);

                $threads = $finder->fetchColumns('thread_id');
                $threadIds = array_column($threads, 'thread_id');

                // cache for 30 minutes
                $cache->save('tApi_NewsFeeds_threadIds', $threadIds, 30 * 60);
            }

            $total = count($threadIds);

            $threadIds = array_slice($threadIds, ($page - 1) * $perPage, $perPage, true);
            $threads = $finder->whereIds($threadIds)
                ->with('api')
                ->with('full')
                ->fetch()
                ->sortByList($threadIds);
        } else {
            $this->applyNewsFeedsFilter($finder);

            $total = $finder->total();
            $threads = $finder->fetch();
        }

        $threads = $threads
            ->filterViewable()
            ->toApiResults(Entity::VERBOSITY_VERBOSE);

        $data = [
            'threads' => $threads,
            'pagination' => $this->getPaginationData($threads, $page, $perPage, $total)
        ];

        return $this->apiResult($data);
    }

    public function actionGetTerms()
    {
        return $this->handleHelpPage('terms');
    }

    public function actionGetPrivacy()
    {
        return $this->handleHelpPage('privacy_policy');
    }

    protected function handleHelpPage($pageId)
    {
        /** @var \XF\Entity\HelpPage|null $page */
        $page = $this->em()->find('XF:HelpPage', $pageId);

        $html = '';
        if (!$page) {
            \XF::logError(sprintf(
                '[tl] Api: Unknown help page with page_id=%s',
                $pageId
            ));
        } else {
            $templater = $this->app()->templater();
            $container = $this->app()->container();

            $container
                ->set('contactUrl', function () use ($container) {
                    $options = $container['options'];
                    $router = $container['router.public'];

                    if (!isset($options->contactUrl['type'])) {
                        return '';
                    }

                    switch ($options->contactUrl['type']) {
                        case 'default':
                            $url = $router->buildLink('canonical:misc/contact/');

                            break;
                        case 'custom':
                            $url = $options->contactUrl['custom'];

                            break;
                        default: $url = '';
                    }

                    return $url;
                });

            $templater->addDefaultParam('xf', $this->app()->getGlobalTemplateData());

            $html = $templater->renderTemplate('public:_help_page_' . $page->page_id, [
                'page' => $page
            ]);
        }

        return $this->apiResult([
            'content' => $html
        ]);
    }

    protected function applyNewsFeedsFilter(Thread $finder)
    {
        $finder->with('api');

        $finder->where('discussion_state', 'visible');
        $finder->where('discussion_type', '<>', 'redirect');

        $finder->order('last_post_date', 'DESC');
        $finder->indexHint('FORCE', 'last_post_date');
    }
}
