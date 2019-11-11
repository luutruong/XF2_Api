<?php

namespace Truonglv\Api\Api\Controller;

use XF\Finder\Thread;
use XF\Repository\AddOn;
use XF\Mvc\Entity\Entity;
use Truonglv\Api\Util\Token;
use XF\ControllerPlugin\Login;
use Truonglv\Api\Data\Reaction;
use XF\Service\User\Registration;
use Truonglv\Api\Entity\AccessToken;
use Truonglv\Api\Util\PasswordDecrypter;
use XF\Api\Controller\AbstractController;

class App extends AbstractController
{
    public function actionGet()
    {
        /** @var Reaction $reactionData */
        $reactionData = $this->data('Truonglv\Api:Reaction');

        /** @var AddOn $addOnRepo */
        $addOnRepo = $this->repository('XF:AddOn');
        $addOns = $addOnRepo->getInstalledAddOnData();

        $data = [
            'reactions' => $reactionData->getReactions(),
            'apiVersion' => $addOns['Truonglv/Api']['version_id']
        ];

        return $this->apiResult($data);
    }

    /** @noinspection PhpUnused */
    public function actionGetNewsFeeds()
    {
        /** @var Thread $finder */
        $finder = $this->finder('XF:Thread');

        $page = $this->filterPage();
        $perPage = $this->options()->discussionsPerPage;

        $filters = $this->getNewsFeedsFilters();
        $queryHash = md5('tApi_NewsFeeds_threadIds' . __METHOD__ . strval(json_encode($filters)));

        // 5 minutes
        $ttl = 300;

        /** @var \XF\Entity\Search|null $search */
        $search = $this->em()->findOne('XF:Search', [
            'query_hash' => $queryHash
        ]);
        if ($search !== null) {
            if (($search->search_date + $ttl) <= \XF::$time || $search->result_count === 0) {
                $search->delete();
                $search = null;
            }
        }

        if ($search === null) {
            $this->applyNewsFeedsFilter($finder, $filters);
            $finder->limit($this->options()->maximumSearchResults);

            $results = $finder->fetchColumns('thread_id');
            $searchResults = [];
            foreach ($results as $result) {
                $searchResults['thread-' . $result['thread_id']] = [
                    'thread',
                    $result['thread_id']
                ];
            }

            if (count($searchResults) === 0) {
                return $this->apiResult([
                    'threads' => []
                ]);
            }

            /** @var \XF\Entity\Search $search */
            $search = $this->em()->create('XF:Search');
            $search->search_type = 'thread';
            $search->query_hash = $queryHash;
            $search->search_results = $searchResults;
            $search->result_count = count($searchResults);
            $search->search_date = \XF::$time;
            $search->user_id = 0;
            $search->search_query = 'tApi_NewsFeeds_threadIds';
            $search->search_order = 'date';
            $search->search_grouping = 1;
            $search->search_constraints = [];

            $search->save();
        }

        $threadIds = [];
        $data = [
            'threads' => []
        ];

        foreach ($search->search_results as $result) {
            $threadIds[] = $result[1];
        }

        $threadIds = array_slice($threadIds, ($page - 1) * $perPage, $perPage, true);
        if (count($threadIds) > 0) {
            $this->request()->set(\Truonglv\Api\App::PARAM_KEY_INCLUDE_MESSAGE_HTML, true);

            $threads = $finder->whereIds($threadIds)
                ->with('api')
                ->with('full')
                ->fetch()
                ->sortByList($threadIds);

            $data['threads'] = $threads->filterViewable()->toApiResults(Entity::VERBOSITY_VERBOSE, [
                'tapi_first_post' => true
            ]);
            if ($search->result_count > $perPage) {
                $data['pagination'] = $this->getPaginationData($threads, $page, $perPage, $search->result_count);
            }
        }

        return $this->apiResult($data);
    }

    /** @noinspection PhpUnused */
    public function actionGetTerms()
    {
        return $this->handleHelpPage('terms');
    }

    /** @noinspection PhpUnused */
    public function actionGetPrivacy()
    {
        return $this->handleHelpPage('privacy_policy');
    }

    /** @noinspection PhpUnused */
    public function actionPostSubscriptions()
    {
        $this->assertRequiredApiInput([
            'device_token',
            'type'
        ]);

        $visitor = \XF::visitor();
        if ($visitor->user_id <= 0) {
            return $this->noPermission();
        }

        $input = $this->filter([
            'device_token' => 'str',
            'is_device_test' => 'bool',
            'provider' => 'str',
            'provider_key' => 'str',
            'type' => 'str'
        ]);

        /** @var \Truonglv\Api\Service\Subscription $service */
        $service = $this->service('Truonglv\Api:Subscription', \XF::visitor(), $input['device_token']);

        if ($input['type'] === 'unsubscribe') {
            $service->unsubscribe();
        } elseif ($input['type'] === 'subscribe') {
            $this->assertRequiredApiInput([
                'provider',
                'provider_key',
            ]);

            unset($input['device_token'], $input['type']);
            $input['app_version'] = $this->request()->getServer(\Truonglv\Api\App::HEADER_KEY_APP_VERSION);

            $service->subscribe($input);
        }

        return $this->apiSuccess();
    }

    /** @noinspection PhpUnused */
    public function actionPostRegister()
    {
        $this->assertRequiredApiInput([
            'username',
            'email',
            'password'
        ]);

        $visitor = \XF::visitor();
        if ($visitor->user_id > 0) {
            return $this->noPermission();
        }

        $password = $this->filter('password', 'str');
        $decrypted = '';

        try {
            $decrypted = PasswordDecrypter::decrypt($password, $this->options()->tApi_encryptKey);
        } catch (\InvalidArgumentException $e) {
        }

        $input = $this->filter([
            'username' => 'str',
            'email' => 'str'
        ]);

        /** @var Registration $registration */
        $registration = $this->service('XF:User\Registration');
        $registration->setFromInput($input);
        $registration->setPassword($decrypted, '', false);

        if (!$registration->validate($errors)) {
            return $this->error($errors, 400);
        }

        /** @var \XF\Entity\User $user */
        $user = $registration->save();

        return $this->apiSuccess([
            'user' => $user->toApiResult(),
            'accessToken' => Token::generateAccessToken($user->user_id, $this->options()->tApi_accessTokenTtl)
        ]);
    }

    /** @noinspection PhpUnused */
    public function actionPostAuth()
    {
        $this->assertRequiredApiInput(['username', 'password']);

        $visitor = \XF::visitor();
        if ($visitor->user_id > 0) {
            return $this->noPermission();
        }

        $encrypted = $this->filter('password', 'str');
        $password = '';

        try {
            $password = PasswordDecrypter::decrypt($encrypted, $this->options()->tApi_encryptKey);
        } catch (\InvalidArgumentException $e) {
        }

        $username = $this->filter('username', 'str');
        $ip = $this->request()->getIp();

        /** @var \XF\Service\User\Login $loginService */
        $loginService = $this->service('XF:User\Login', $username, $ip);
        if ($loginService->isLoginLimited($limitType)) {
            return $this->error(\XF::phrase('your_account_has_temporarily_been_locked_due_to_failed_login_attempts'), 400);
        }

        /** @var \XF\Entity\User|null $user */
        $user = $loginService->validate($password, $error);
        if (!$user) {
            return $this->error($error);
        }

        /** @var Login $loginPlugin */
        $loginPlugin = $this->plugin('XF:Login');
        if ($loginPlugin->isTfaConfirmationRequired($user)) {
            $provider = $this->filter('provider', 'str');
            if (!$this->request()->exists('code')) {
                return $this->apiError(
                    \XF::phrase('two_step_verification_required'),
                    'two_step_verification_required',
                    null,
                    100
                );
            }

            /** @var \XF\Service\User\Tfa $tfaService */
            $tfaService = $this->service('XF:User\Tfa', $user);
            if ($tfaService->isTfaAvailable()) {
                if (!$tfaService->isProviderValid($provider)) {
                    return $this->error(\XF::phrase('two_step_verification_value_could_not_be_confirmed'), 400);
                }

                if (!$tfaService->verify($this->request(), $provider)) {
                    return $this->error(\XF::phrase('two_step_verification_value_could_not_be_confirmed'), 400);
                }
            }
        }

        return $this->apiSuccess([
            'user' => $user->toApiResult(),
            'accessToken' => Token::generateAccessToken($user->user_id, $this->options()->tApi_accessTokenTtl)
        ]);
    }

    /** @noinspection PhpUnused */
    public function actionPostRefreshToken()
    {
        $this->assertRequiredApiInput(['token']);

        /** @var AccessToken|null $token */
        $token = $this->finder('Truonglv\Api:AccessToken')
            ->with('User', true)
            ->whereId($this->filter('token', 'str'))
            ->fetchOne();
        if (!$token) {
            return $this->notFound();
        }

        $token->renewExpires();
        $token->save();

        return $this->apiSuccess([
            'user' => $token->User->toApiResult(),
            'accessToken' => $token->token
        ]);
    }

    /**
     * @param string $pageId
     * @return \XF\Api\Mvc\Reply\ApiResult
     */
    protected function handleHelpPage(string $pageId)
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

    /**
     * @return array
     */
    protected function getNewsFeedsFilters()
    {
        $filters = [];

        $input = $this->filter([
            'order' => 'str'
        ]);

        $availableOrders = $this->getNewsFeedsAvailableSorts();
        if (isset($availableOrders[$input['order']])) {
            $order = $availableOrders[$input['order']];

            if ($order instanceof \Closure) {
                $filters['orderCallback'] = $order;
            } else {
                $filters['order'] = $order[0];
                $filters['direction'] = $order[1];
            }
        }

        return $filters;
    }

    /**
     * @return array
     */
    protected function getNewsFeedsAvailableSorts()
    {
        return [
            'new_threads' => ['post_date', 'DESC'],
            'recent_threads' => ['last_post_date', 'DESC'],
            'trending' => function (Thread $finder) {
                $finder->order('view_count', 'DESC');
                // only fetch threads in 7 days!
                $finder->where('post_date', '>=', \XF::$time - 7 * 86400);
            }
        ];
    }

    /**
     * @param Thread $finder
     * @param array $filters
     * @return void
     */
    protected function applyNewsFeedsFilter(Thread $finder, array $filters)
    {
        $finder->with('api');
        $finder->with('FirstPost');

        $finder->where('discussion_state', 'visible');
        $finder->where('discussion_type', '<>', 'redirect');

        if (isset($filters['order']) && isset($filters['direction'])) {
            $finder->order($filters['order'], $filters['direction']);
        } elseif (isset($filters['orderCallback'])) {
            call_user_func($filters['orderCallback'], $finder);
        } else {
            $finder->order('last_post_date', 'DESC');
            $finder->indexHint('FORCE', 'last_post_date');
        }
    }
}
