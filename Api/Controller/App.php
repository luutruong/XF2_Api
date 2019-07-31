<?php

namespace Truonglv\Api\Api\Controller;

use XF\Finder\Thread;
use XF\Mvc\Entity\Entity;
use Truonglv\Api\Entity\Subscription;
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

    public function actionPostSubscriptions()
    {
        $this->assertRequiredApiInput([
            'device_token',
            'type'
        ]);

        $visitor = \XF::visitor();
        if (!$visitor->user_id) {
            return $this->noPermission();
        }

        $input = $this->filter([
            'device_token' => 'str',
            'device_type' => 'str',
            'is_device_test' => 'bool',
            'provider' => 'str',
            'provider_key' => 'str',
            'type' => 'str'
        ]);

        if ($input['type'] === 'unsubscribe') {
            /** @var Subscription[] $subscriptions */
            $subscriptions = $this->finder('Truonglv\Api:Subscription')
                ->where('user_id', $visitor->user_id)
                ->where('device_token', $input['device_token'])
                ->fetch();
            foreach ($subscriptions as $subscription) {
                $subscription->delete();
            }
        } elseif ($input['type'] === 'subscribe') {
            $this->assertRequiredApiInput([
                'provider',
                'provider_key',
            ]);

            /** @var Subscription|null $exists */
            $exists = $this->finder('Truonglv\Api:Subscription')
                ->where('user_id', $visitor->user_id)
                ->where('device_token', $input['device_token'])
                ->fetchOne();

            if ($exists) {
                $subscription = $exists;
            } else {
                /** @var Subscription $subscription */
                $subscription = $this->em()->create('Truonglv\Api:Subscription');
                $subscription->user_id = $visitor->user_id;
                $subscription->username = $visitor->username;
                $subscription->device_token = $input['device_token'];
            }

            $subscription->app_version = $this->request()->getServer(\Truonglv\Api\App::HEADER_KEY_APP_VERSION);
            $subscription->subscribed_date = \XF::$time;
            $subscription->is_device_test = $input['is_device_test'];
            $subscription->provider = $input['provider'];
            $subscription->provider_key = $input['provider_key'];

            $subscription->save();
        }

        return $this->apiSuccess();
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
