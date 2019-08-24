<?php

namespace Truonglv\Api\Api\Controller;

use XF\Finder\Thread;
use XF\Mvc\Entity\Entity;
use Truonglv\Api\Util\Token;
use XF\ControllerPlugin\Login;
use XF\Service\User\Registration;
use Truonglv\Api\Entity\AccessToken;
use Truonglv\Api\Util\PasswordDecrypter;
use XF\Api\Controller\AbstractController;

class App extends AbstractController
{
    public function actionGet()
    {
        $activeReactions = $this->finder('XF:Reaction')
            ->where('active', true)
            ->order('display_order')
            ->fetch();
        $reactions = $this->options()->tApi_reactions;
        $validReactions = [];

        if (count($reactions) === 0) {
            $validReactions[1] = [
                'imageUrl' => 'styles/default/Truonglv/Api/like.png',
                'text' => $activeReactions[1]->title,
                'reactionId' => 1
            ];
        } else {
            foreach ($reactions as $reaction) {
                if (isset($activeReactions[$reaction['reactionId']])) {
                    $validReactions[$reaction['reactionId']] = [
                        'imageUrl' => $reaction['imageUrl'],
                        'text' => $activeReactions[$reaction['reactionId']]->title,
                        'reactionId' => $reaction['reactionId']
                    ];
                }
            }
        }

        $pather = $this->app()->container('request.pather');
        foreach ($validReactions as &$reaction) {
            $reaction['imageUrl'] = $pather($reaction['imageUrl'], 'full');
        }

        $data = [
            'reactions' => $validReactions
        ];

        return $this->apiResult($data);
    }

    /** @noinspection PhpUnused */
    public function actionGetNewsFeeds()
    {
        $cache = $this->app()->cache();
        /** @var Thread $finder */
        $finder = $this->finder('XF:Thread');

        $page = $this->filterPage();
        $perPage = $this->options()->discussionsPerPage;

        if ($cache) {
            $threadIds = $cache->fetch('tApi_NewsFeeds_threadIds');
            if ($threadIds === false) {
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
     * @param Thread $finder
     * @return void
     */
    protected function applyNewsFeedsFilter(Thread $finder)
    {
        $finder->with('api');

        $finder->where('discussion_state', 'visible');
        $finder->where('discussion_type', '<>', 'redirect');

        $finder->order('last_post_date', 'DESC');
        $finder->indexHint('FORCE', 'last_post_date');
    }
}
