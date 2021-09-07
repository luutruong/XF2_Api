<?php

namespace Truonglv\Api\Api\Controller;

use XF\Http\Request;
use XF\Finder\Thread;
use XF\Mvc\Dispatcher;
use XF\Repository\Tag;
use XF\Repository\Tfa;
use XF\Mvc\Reply\Error;
use XF\Repository\Node;
use XF\Repository\User;
use XF\Repository\AddOn;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Reply\Message;
use XF\Mvc\Reply\Exception;
use Truonglv\Api\Util\Token;
use XF\Repository\Attachment;
use XF\ControllerPlugin\Login;
use Truonglv\Api\Data\Reaction;
use XF\Api\Mvc\Reply\ApiResult;
use XF\Mvc\Reply\AbstractReply;
use Truonglv\Api\Util\Encryption;
use XF\Service\User\Registration;
use Truonglv\Api\Entity\AccessToken;
use Truonglv\Api\Entity\Subscription;
use XF\Api\Controller\AbstractController;

class App extends AbstractController
{
    public function actionGet()
    {
        $data = $this->getAppInfo();

        return $this->apiResult($data);
    }

    public function actionGetNewsFeeds()
    {
        $page = $this->filterPage();
        $perPage = $this->options()->tApi_recordsPerPage;

        $filters = $this->getNewsFeedsFilters();
        $queryHash = md5(uniqid('', true));

        $searchId = $this->filter('search_id', 'uint');
        $searchQuery = 'tApi_actionGetNewsFeeds';
        $visitor = \XF::visitor();
        /** @var \XF\Entity\Search|null $search */
        $search = null;
        if ($searchId > 0) {
            /** @var \XF\Entity\Search|null $existingSearch */
            $existingSearch = $this->em()->find('XF:Search', $searchId);
            if ($existingSearch !== null
                && $existingSearch->user_id === $visitor->user_id
                && $existingSearch->search_query === $searchQuery
                && ($existingSearch->search_date + 3600) >= time()
            ) {
                $search = $existingSearch;
            }
        }

        if ($search === null) {
            /** @var Thread $finder */
            $finder = $this->finder('XF:Thread');

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

            if (\count($searchResults) === 0) {
                return $this->apiResult([
                    'threads' => []
                ]);
            }

            /** @var \XF\Entity\Search $search */
            $search = $this->em()->create('XF:Search');
            $search->search_type = 'thread';
            $search->search_query = $searchQuery;
            $search->query_hash = $queryHash;
            $search->search_results = $searchResults;
            $search->result_count = \count($searchResults);
            $search->search_date = \XF::$time;
            $search->user_id = $visitor->user_id;
            $search->search_order = 'date';
            $search->search_grouping = true;
            $search->search_constraints = [];

            $search->save();
        }

        $threadIds = [];
        $data = [
            'threads' => [],
            'pagination' => [
                'current_page' => $page,
                'last_page' => 1,
            ],
            'search_id' => $search->search_id,
        ];

        foreach ($search->search_results as $result) {
            $threadIds[] = $result[1];
        }

        $threadIds = \array_slice($threadIds, ($page - 1) * $perPage, $perPage, true);
        if (\count($threadIds) === 0) {
            return $this->apiResult([
                'threads' => [],
            ]);
        }

        $newFinder = $this->finder('XF:Thread');
        $threads = $newFinder->whereIds($threadIds)
            ->with('FirstPost')
            ->with('api')
            ->fetch()
            ->sortByList($threadIds);
        $posts = $this->em()->getEmptyCollection();
        /** @var \XF\Entity\Thread $thread */
        foreach ($threads as $thread) {
            $posts[$thread->first_post_id] = $thread->FirstPost;
        }

        /** @var Attachment $attachmentRepo */
        $attachmentRepo = $this->repository('XF:Attachment');
        $attachmentRepo->addAttachmentsToContent($posts, 'post');

        $data['threads'] = $threads->filterViewable()->toApiResults(Entity::VERBOSITY_NORMAL, [
            'tapi_first_post' => true,
            'tapi_fetch_image' => true
        ]);
        if ($search->result_count > $perPage) {
            $data['pagination'] = $this->getPaginationData($threads, $page, $perPage, $search->result_count);
        }

        $maxPages = ceil($search->result_count / $perPage);
        if ($page < $maxPages) {
            $data['next_url'] = $this->buildLink('canonical:tapi-apps/news-feeds', null, [
                'search_id' => $search->search_id,
                'page' => $page + 1,
            ]);
        }

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

    public function actionGetTrendingTags()
    {
        /** @var Tag $tagRepo */
        $tagRepo = $this->repository('XF:Tag');
        $enableTagging = (bool) $this->options()->enableTagging;
        if (!$enableTagging) {
            return $this->apiResult([
                'tags' => [],
            ]);
        }

        $cloudEntries = $tagRepo->getTagsForCloud(30, $this->options()->tagCloudMinUses);
        $tagCloud = $tagRepo->getTagCloud($cloudEntries);
        $tagNames = [];
        foreach ($tagCloud as $item) {
            $tagNames[] = $item['tag']['tag'];
        }

        return $this->apiResult([
            'tags' => $tagNames,
        ]);
    }

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
            'type' => 'str',
            'device_type' => 'str',
        ]);

        /** @var \Truonglv\Api\Service\Subscription $service */
        $service = $this->service('Truonglv\Api:Subscription', \XF::visitor(), $input['device_token']);

        $extra = [];
        if ($input['type'] === 'unsubscribe') {
            $service->unsubscribe();
        } elseif ($input['type'] === 'subscribe') {
            $this->assertRequiredApiInput([
                'provider',
                'provider_key',
            ]);

            $dupes = $this->finder('Truonglv\Api:Subscription')
                ->where('device_token', $input['device_token'])
                ->where('provider', $input['provider'])
                ->fetch();
            /** @var Subscription $dupe */
            foreach ($dupes as $dupe) {
                if ($dupe->user_id == $visitor->user_id) {
                    continue;
                }

                $dupe->delete();
            }

            unset($input['device_token'], $input['type']);
            $input['app_version'] = $this->request()->getServer(\Truonglv\Api\App::HEADER_KEY_APP_VERSION);

            $subscription = $service->subscribe($input);
            $extra['subscription'] = $subscription->toApiResult();
        }

        return $this->apiSuccess($extra);
    }

    public function actionPostRegister()
    {
        if (!$this->options()->registrationSetup['enabled']) {
            return $this->error(\XF::phrase('new_registrations_currently_not_being_accepted'), 400);
        }

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
            $decrypted = Encryption::decrypt($password, $this->options()->tApi_encryptKey);
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

        if ($this->request()->exists('birthday')) {
            /** @var \DateTime|null $birthday */
            $birthday = $this->filter('birthday', 'datetime,obj');
            if ($birthday === null) {
                return $this->error(\XF::phrase('please_enter_valid_date_of_birth'));
            }

            $registration->setDob(
                $birthday->format('j'),
                $birthday->format('n'),
                $birthday->format('Y')
            );
        }

        if (!$registration->validate($errors)) {
            return $this->error($errors, 400);
        }

        /** @var \XF\Entity\User $user */
        $user = $registration->save();

        return $this->apiSuccess([
            'user' => $user->toApiResult(Entity::VERBOSITY_VERBOSE),
            'accessToken' => Token::generateAccessToken($user->user_id, $this->options()->tApi_accessTokenTtl)
        ]);
    }

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
            $password = Encryption::decrypt($encrypted, $this->options()->tApi_encryptKey);
        } catch (\InvalidArgumentException $e) {
        }

        $username = $this->filter('username', 'str');
        $user = $this->verifyUserCredentials($username, $password);

        return $this->apiSuccess([
            'user' => $user->toApiResult(Entity::VERBOSITY_VERBOSE),
            'accessToken' => Token::generateAccessToken($user->user_id, $this->options()->tApi_accessTokenTtl)
        ]);
    }

    public function actionPostBatch()
    {
        $input = $this->request()->getInputRaw();
        $jobs = \json_decode($input, true);

        if (!\is_array($jobs)) {
            return $this->apiError('Invalid batch json format', 'invalid_batch_json_format');
        }

        $jobResults = [];
        $start = microtime(true);

        foreach ($jobs as $job) {
            if (!is_array($job)) {
                continue;
            }

            $job = \array_replace_recursive($this->getDefaultJobOptions(), $job);
            $jobResults[$job['uri']] = $this->runJob($job);
        }

        return $this->apiResult([
            'jobs' => $jobResults,
            '_job_timing' => microtime(true) - $start,
        ]);
    }

    /**
     * @param array $job
     * @return array|null
     */
    protected function runJob(array $job): ?array
    {
        if (!isset($job['uri'])) {
            return null;
        }

        $server = \array_replace($_SERVER, [
            'REQUEST_METHOD' => \strtoupper($job['method'])
        ]);

        $request = new Request($this->app()->inputFilterer(), $job['params'], [], [], $server);
        \Truonglv\Api\App::setRequest($request);

        $request->set('_isApiJob', true);
        $dispatcher = new Dispatcher($this->app(), $request);

        $match = $dispatcher->route($job['uri']);
        $reply = $dispatcher->dispatchLoop($match);

        \Truonglv\Api\App::setRequest(null);

        if ($reply instanceof ApiResult) {
            return [
                '_job_result' => 'ok',
                '_job_response' => $reply->getApiResult(),
            ];
        } elseif ($reply instanceof Error) {
            return [
                '_job_result' => 'error',
                '_job_error' => $reply->getErrors(),
            ];
        } elseif ($reply instanceof Message) {
            return [
                '_job_result' => 'ok',
                '_job_message' => $reply->getMessage(),
            ];
        } elseif ($reply instanceof Exception) {
            return [
                '_job_result' => 'error',
                '_job_error' => $reply->getMessage(),
            ];
        }

        throw new \Exception('Unknown reply (' . get_class($reply) . ') occurred.');
    }

    /**
     * @return array
     */
    protected function getDefaultJobOptions(): array
    {
        return [
            'method' => 'GET',
            'uri' => null,
            'params' => []
        ];
    }

    /**
     * @param string $username
     * @param string $password
     * @return \XF\Entity\User
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function verifyUserCredentials(string $username, string $password)
    {
        $ip = $this->request()->getIp();

        /** @var \XF\Service\User\Login $loginService */
        $loginService = $this->service('XF:User\Login', $username, $ip);
        if ($loginService->isLoginLimited($limitType)) {
            throw $this->errorException(\XF::phrase('your_account_has_temporarily_been_locked_due_to_failed_login_attempts'), 400);
        }

        /** @var \XF\Entity\User|null $user */
        $user = $loginService->validate($password, $error);
        if ($user === null) {
            throw $this->errorException($error, 400);
        }

        if (!$this->runTfaValidation($user)) {
            throw $this->errorException(\XF::phrase('two_step_verification_value_could_not_be_confirmed'));
        }

        return $user;
    }

    /**
     * @param \XF\Entity\User $user
     * @return bool
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function runTfaValidation(\XF\Entity\User $user)
    {
        /** @var Login $loginPlugin */
        $loginPlugin = $this->plugin('XF:Login');
        if (!$loginPlugin->isTfaConfirmationRequired($user)) {
            return true;
        }

        $provider = $this->filter('tfa_provider', 'str');
        /** @var Tfa $tfaRepo */
        $tfaRepo = $this->repository('XF:Tfa');
        $providers = $tfaRepo->getAvailableProvidersForUser($user->user_id);
        $response = $this->app()->response();
        $response->header('X-Api-Tfa-Providers', implode(',', array_keys($providers)));

        $this->assertRequiredApiInput(['tfa_provider']);

        if (!isset($providers[$provider])) {
            throw $this->exception($this->message(\XF::phrase('two_step_verification_required'), 202));
        }

        /** @var \XF\Service\User\Tfa $tfaService */
        $tfaService = $this->service('XF:User\Tfa', $user);

        if (!$tfaService->isTfaAvailable()) {
            return true;
        }

        if ($this->filter('tfa_trigger', 'bool') === true) {
            $tfaService->trigger($this->request(), $provider);

            throw $this->exception($this->message('changes_saved'));
        }

        if ($tfaService->hasTooManyTfaAttempts()) {
            throw $this->errorException(\XF::phrase('your_account_has_temporarily_been_locked_due_to_failed_login_attempts'));
        }

        if (!$tfaService->verify($this->request(), $provider)) {
            throw $this->errorException(\XF::phrase('two_step_verification_value_could_not_be_confirmed'));
        }

        return true;
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
        if ($token === null) {
            return $this->notFound();
        }

        $token->renewExpires();
        $token->save();

        /** @var \XF\Entity\User $user */
        $user = $token->User;

        return $this->apiSuccess([
            'user' => $user->toApiResult(Entity::VERBOSITY_VERBOSE),
            'accessToken' => $token->token
        ]);
    }

    /**
     * @return array
     */
    protected function getAppInfo(): array
    {
        /** @var Reaction $reactionData */
        $reactionData = $this->data('Truonglv\Api:Reaction');
        $reactions = $reactionData->getReactions();

        /** @var AddOn $addOnRepo */
        $addOnRepo = $this->repository('XF:AddOn');
        $addOns = $addOnRepo->getInstalledAddOnData();

        $accountDetails = $this->app()->router('public')->buildLink('canonical:account/account-details');

        /** @var Attachment $attachmentRepo */
        $attachmentRepo = $this->repository('XF:Attachment');
        $constraints = $attachmentRepo->getDefaultAttachmentConstraints();

        $registrationSetup = $this->app()->options()->registrationSetup;

        return [
            'reactions' => $reactions,
            'apiVersion' => $addOns['Truonglv/Api']['version_id'],
            'allowRegistration' => (bool) $this->options()->registrationSetup['enabled'],
            'defaultReactionId' => \Truonglv\Api\Option\Reaction::DEFAULT_REACTION_ID,
            'defaultReactionText' => $reactions[\Truonglv\Api\Option\Reaction::DEFAULT_REACTION_ID]['text'],
            'accountUpdateUrl' => \Truonglv\Api\App::buildLinkProxy($accountDetails),
            'quotePlaceholderTemplate' => \Truonglv\Api\App::QUOTE_PLACEHOLDER_TEMPLATE,
            'allowedAttachmentExtensions' => $constraints['extensions'],
            'registerMinimumAge' => $registrationSetup['requireDob'] > 0
                ? intval($registrationSetup['minimumAge'])
                : 0,
        ];
    }

    protected function handleHelpPage(string $pageId): AbstractReply
    {
        /** @var \XF\Entity\HelpPage|null $page */
        $page = $this->em()->find('XF:HelpPage', $pageId);

        $html = '';
        if ($page === null) {
            \XF::logError(\sprintf(
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
    protected function getNewsFeedsFilters(): array
    {
        return [];
    }

    /**
     * @param Thread $finder
     * @param array $filters
     * @return void
     */
    protected function applyNewsFeedsFilter(Thread $finder, array $filters)
    {
        $finder->where('discussion_state', 'visible');
        $finder->where('discussion_type', '<>', 'redirect');

        /** @var Node $nodeRepo */
        $nodeRepo = $this->repository('XF:Node');
        /** @var User $userRepo */
        $userRepo = $this->repository('XF:User');
        $guest = $userRepo->getGuestUser();

        $nodes = \XF::asVisitor($guest, function () use ($nodeRepo) {
            return $nodeRepo->getNodeList();
        });

        $forumIds = [];
        /** @var \XF\Entity\Node $node */
        foreach ($nodes as $node) {
            if ($node->node_type_id === 'Forum') {
                $forumIds[] = $node->node_id;
            }
        }
        if (\count($forumIds) > 0) {
            $finder->where('node_id', $forumIds);
        } else {
            $finder->whereImpossible();
        }

        $finder->order('last_post_date', 'DESC');
        $finder->indexHint('FORCE', 'last_post_date');
    }
}
