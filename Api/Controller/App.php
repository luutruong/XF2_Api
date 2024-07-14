<?php

namespace Truonglv\Api\Api\Controller;

use XF;
use DateTime;
use Throwable;
use function md5;
use function ceil;
use function count;
use LogicException;
use function strlen;
use XF\Http\Request;
use XF\Finder\Thread;
use function in_array;
use XF\Mvc\Dispatcher;
use XF\Repository\Tfa;
use XF\Mvc\Reply\Error;
use XF\Repository\Node;
use function strtoupper;
use function array_merge;
use function array_slice;
use function json_decode;
use function json_encode;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Reply\Message;
use XF\Mvc\Reply\Exception;
use InvalidArgumentException;
use XF\Repository\Attachment;
use XF\ControllerPlugin\Login;
use XF\Api\Mvc\Reply\ApiResult;
use Truonglv\Api\XF\Entity\User;
use Truonglv\Api\Util\Encryption;
use XF\Service\User\Registration;
use Truonglv\Api\Entity\IAPProduct;
use XF\Entity\UserConnectedAccount;
use XF\Repository\ConnectedAccount;
use Truonglv\Api\Entity\AccessToken;
use function array_replace_recursive;
use Truonglv\Api\Entity\RefreshToken;
use Truonglv\Api\Entity\Subscription;
use OAuth\Common\Token\TokenInterface;
use OAuth\OAuth2\Token\StdOAuth2Token;
use Truonglv\Api\Payment\IAPInterface;
use XF\Entity\ConnectedAccountProvider;
use XF\Api\Controller\AbstractController;
use Truonglv\Api\Payment\PurchaseExpiredException;
use Truonglv\Api\XF\ConnectedAccount\Storage\StorageState;

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

        $searchId = $this->filter('search_id', 'uint');
        $searchQuery = 'tApi_actionGetNewsFeeds_' . __METHOD__;
        $visitor = XF::visitor();
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
            $search = $this->runNewsFeedSearch($searchQuery);
            if ($search === null) {
                return $this->apiResult([
                    'threads' => [],
                    'search_id' => 0
                ]);
            }
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

        $threadIds = array_slice($threadIds, ($page - 1) * $perPage, $perPage, true);
        if (count($threadIds) === 0) {
            return $this->apiResult([
                'threads' => [],
            ]);
        }

        $newFinder = $this->finder('XF:Thread');
        $threads = $newFinder->whereIds($threadIds)
            ->with(['FirstPost', 'LastPost'])
            ->with('api')
            ->fetch()
            ->sortByList($threadIds);
        $posts = $this->em()->getEmptyCollection();
        /** @var \XF\Entity\Thread $thread */
        foreach ($threads as $thread) {
            $posts[$thread->first_post_id] = $thread->FirstPost;
            if ($thread->last_post_id > 0) {
                $posts[$thread->last_post_id] = $thread->LastPost;
            }
        }

        /** @var Attachment $attachmentRepo */
        $attachmentRepo = $this->repository('XF:Attachment');
        $attachmentRepo->addAttachmentsToContent($posts, 'post');

        $data['threads'] = $threads->filterViewable()->toApiResults(Entity::VERBOSITY_NORMAL, [
            'tapi_first_post' => true,
            'tapi_fetch_image' => true,
            'tapi_last_post' => true,
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

    public function actionGetTrendingTags()
    {
        /** @var \Truonglv\Api\XF\Repository\Tag $tagRepo */
        $tagRepo = $this->repository('XF:Tag');
        $enableTagging = (bool) $this->options()->enableTagging;
        if (!$enableTagging) {
            return $this->apiResult([
                'tags' => [],
            ]);
        }

        return $this->apiResult([
            'tags' => $tagRepo->getTApiTrendingTags(['thread'], 7, 30),
        ]);
    }

    public function actionPostLogOut()
    {
        $accessToken = $this->request()->getServer(\Truonglv\Api\App::HEADER_KEY_ACCESS_TOKEN);

        /** @var AccessToken|null $token */
        $token = $this->em()->find('Truonglv\Api:AccessToken', $accessToken);
        if ($token !== null) {
            $token->delete();
        }

        return $this->apiSuccess();
    }

    public function actionPostSubscriptions()
    {
        $this->assertRequiredApiInput([
            'device_token',
            'type'
        ]);

        $visitor = XF::visitor();
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
        $service = $this->service('Truonglv\Api:Subscription', XF::visitor(), $input['device_token']);

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
            return $this->error(XF::phrase('new_registrations_currently_not_being_accepted'), 400);
        }

        $this->assertRequiredApiInput([
            'username',
            'email',
            'password'
        ]);

        $visitor = XF::visitor();
        if ($visitor->user_id > 0) {
            return $this->noPermission();
        }

        $password = $this->filter('password', 'str');
        $decrypted = '';

        try {
            $decrypted = Encryption::decrypt($password, $this->options()->tApi_encryptKey);
        } catch (InvalidArgumentException $e) {
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
            /** @var DateTime|null $birthday */
            $birthday = $this->filter('birthday', 'datetime,obj');
            if ($birthday === null) {
                return $this->error(XF::phrase('please_enter_valid_date_of_birth'));
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

        return $this->apiSuccess($this->getAuthResultData($user));
    }

    public function actionPostAuth()
    {
        $this->assertRequiredApiInput(['username', 'password']);

        $visitor = XF::visitor();
        if ($visitor->user_id > 0) {
            return $this->noPermission();
        }

        $encrypted = $this->filter('password', 'str');
        $password = $this->decryptPassword($encrypted);

        $username = $this->filter('username', 'str');
        $user = $this->verifyUserCredentials($username, $password);

        return $this->apiSuccess($this->getAuthResultData($user));
    }

    public function actionPostBatch()
    {
        $input = $this->request()->getInputRaw();
        $jobs = json_decode($input, true);

        if (!\is_array($jobs)) {
            return $this->apiError('Invalid batch json format', 'invalid_batch_json_format');
        }

        $jobResults = [];
        $start = microtime(true);

        foreach ($jobs as $job) {
            if (!is_array($job)) {
                continue;
            }

            $job = array_replace_recursive($this->getDefaultJobOptions(), $job);
            $jobResults[$job['uri']] = $this->runJob($job);
        }

        return $this->apiResult([
            'jobs' => $jobResults,
            '_job_timing' => microtime(true) - $start,
        ]);
    }

    public function actionPostConnectedAccounts()
    {
        $this->assertRequiredApiInput(['provider', 'token']);

        /** @var ConnectedAccountProvider|null $provider */
        $provider = $this->em()->find('XF:ConnectedAccountProvider', $this->filter('provider', 'str'));
        if ($provider === null) {
            return $this->error(XF::phrase('connected_account_provider_specified_cannot_be_found'), 404);
        }

        $handler = $provider->getHandler();
        if (!$provider->isUsable()) {
            throw $this->exception(
                $this->error(XF::phrase('this_connected_account_provider_is_not_currently_available'))
            );
        }

        $visitor = XF::visitor();
        if ($visitor->user_id > 0) {
            return $this->noPermission();
        }

        $type = $this->filter('type', 'str');
        if (!in_array($type, ['', 'new', 'existing', 'test'], true)) {
            return $this->noPermission();
        }

        $input = $this->filter([
            'username' => 'str',
            'email' => 'str',
            'password' => 'str',
        ]);

        if (strlen($input['password']) > 0) {
            $input['password'] = $this->decryptPassword($input['password']);
        }

        /** @var ConnectedAccount $connectedAccountRepo */
        $connectedAccountRepo = $this->repository('XF:ConnectedAccount');
        $tokenText = $this->filter('token', 'str');

        /** @var User|null $associateUser */
        $associateUser = null;
        if ($type === 'existing') {
            $associateUser = $this->verifyUserCredentials($input['username'], $input['password']);
            /** @var StorageState $storageState */
            $storageState = $handler->getStorageState($provider, $associateUser);
        } else {
            /** @var StorageState $storageState */
            $storageState = $handler->getStorageState($provider, $visitor);
        }

        $storageState->setTApiStorageType('local');

        $token = new StdOAuth2Token();
        $token->setAccessToken($tokenText);
        $token->setEndOfLife(TokenInterface::EOL_UNKNOWN);
        $storageState->storeToken($token);

        $providerData = $handler->getProviderData($storageState);

        if (!$storageState->getProviderToken()) {
            return $this->error(XF::phrase('error_occurred_while_connecting_with_x', ['provider' => $provider->title]));
        }

        /** @var UserConnectedAccount|null $userConnected */
        $userConnected = $connectedAccountRepo->getUserConnectedAccountFromProviderData($providerData);
        if ($userConnected !== null && $userConnected->User !== null) {
            if (($associateUser !== null && $associateUser->user_id !== $userConnected->user_id)
                || $type === 'new'
            ) {
                return $this->error(XF::phrase('this_accounts_email_is_already_associated_with_another_member', [
                    'provider' => $provider->title,
                    'boardTitle' => $this->options()->boardTitle,
                ]));
            }

            $userConnected->extra_data = $providerData->getExtraData();
            $userConnected->save();

            return $this->apiSuccess($this->getAuthResultData($userConnected->User));
        }

        if ($type === 'test') {
            return $this->error(XF::phrase('there_is_no_valid_connected_account_request_available_at_this_time'), 400);
        }

        if ($associateUser !== null) {
            $connectedAccountRepo->associateConnectedAccountWithUser($associateUser, $providerData);

            return $this->apiSuccess($this->getAuthResultData($associateUser));
        }

        $user = $this->createExternalAccount($providerData, $provider, $input);
        $connectedAccountRepo->associateConnectedAccountWithUser($user, $providerData);

        return $this->apiSuccess($this->getAuthResultData($user));
    }

    public function actionGetForumName()
    {
        $this->assertRequiredApiInput(['name']);

        /** @var XF\Entity\Node|null $node */
        $node = $this->finder('XF:Node')
            ->where('node_name', $this->filter('name', 'str'))
            ->fetchOne();
        if ($node === null) {
            return $this->notFound();
        }

        return $this->rerouteController('XF:Api:Forum', 'get', [
            'node_id' => $node->node_id,
        ]);
    }

    public function actionGetIAPProducts()
    {
        $this->assertRegisteredUser();

        $products = $this->finder('Truonglv\Api:IAPProduct')
            ->with('UserUpgrade', true)
            ->where('active', true)
            ->order('display_order')
            ->fetch();

        return $this->apiResult([
            'products' => $products->toApiResults(),
        ]);
    }

    public function actionPostIAPVerify()
    {
        $this->assertRegisteredUser();
        $this->assertRequiredApiInput(['platform', 'store_product_id']);

        $platform = $this->filter('platform', 'str');
        if ($platform === 'ios') {
            $this->assertRequiredApiInput(['receipt']);
        } else {
            $this->assertRequiredApiInput(['package_name', 'token']);
        }

        $storeProductId = $this->filter('store_product_id', 'str');

        if (!in_array($platform, ['ios', 'android'], true)) {
            return $this->noPermission();
        }

        /** @var IAPProduct|null $product */
        $product = $this->finder('Truonglv\Api:IAPProduct')
            ->where('platform', $platform)
            ->where('store_product_id', $storeProductId)
            ->fetchOne();
        if ($product === null) {
            // unverified
            return $this->error(XF::phrase('tapi_iap_product_not_found'), 400);
        }

        if ($product->isSubscribed()) {
            return $this->error(XF::phrase('tapi_iap_product_already_subscribed'), 400);
        }

        if ($platform === 'ios') {
            $jsonPayload = [
                'transactionReceipt' => $this->filter('receipt', 'str'),
            ];
        } else {
            $jsonPayload = [
                'package_name' => $this->filter('package_name', 'str'),
                'token' => $this->filter('token', 'str'),
                'subscription_id' => $storeProductId,
                'purchase' => $this->filter('purchase', 'str'),
            ];

            $jsonPayload['purchase'] = \GuzzleHttp\json_decode($jsonPayload['purchase'], true);
        }

        /** @var IAPInterface|XF\Payment\AbstractProvider $handler */
        $handler = $product->PaymentProfile->Provider->handler;
        $visitor = XF::visitor();

        /** @var XF\Entity\PurchaseRequest $purchaseRequest */
        $purchaseRequest = $this->em()->create('XF:PurchaseRequest');
        $purchaseRequest->payment_profile_id = $product->payment_profile_id;
        $purchaseRequest->request_key = XF\Util\Random::getRandomString(32);
        $purchaseRequest->user_id = $visitor->user_id;
        $purchaseRequest->provider_id = $product->PaymentProfile->provider_id;
        $purchaseRequest->purchasable_type_id = 'user_upgrade';
        $purchaseRequest->cost_amount = $product->UserUpgrade->cost_amount;
        $purchaseRequest->cost_currency = $product->UserUpgrade->cost_currency;
        $purchaseRequest->provider_metadata = null;
        $purchaseRequest->extra_data = [
            'user_upgrade_id' => $product->user_upgrade_id,
            'store_product_id' => $product->store_product_id,
        ];
        $purchaseRequest->save();

        try {
            $data = $handler->verifyIAPTransaction($purchaseRequest, $jsonPayload);
            $subscriberId = $data['subscriber_id'];
            $transactionId = $data['transaction_id'];
        } catch (PurchaseExpiredException $e) {
            return $this->apiError(
                XF::phrase('tapi_iap_purchase_was_expired'),
                'purchase_expired'
            );
        } catch (Throwable $e) {
            $this->app()->logException($e, false);

            return $this->error(XF::phrase('something_went_wrong_please_try_again'));
        }

        if ($transactionId === null || $subscriberId === null) {
            return $this->error(XF::phrase('something_went_wrong_please_try_again'));
        }

        $purchaseRequest->fastUpdate('provider_metadata', $subscriberId);

        /** @var XF\Entity\PaymentProviderLog|null $log */
        $log = $this->finder('XF:PaymentProviderLog')
            ->where('provider_id', $handler->getProviderId())
            ->where('transaction_id', $transactionId)
            ->where('log_type', 'payment')
            ->order('log_date', 'desc')
            ->fetchOne();
        if ($log !== null) {
            return $this->error(XF::phrase('tapi_your_account_has_been_upgraded'));
        }

        /** @var XF\Repository\Payment $paymentRepo */
        $paymentRepo = $this->repository('XF:Payment');
        $paymentRepo->logCallback(
            $purchaseRequest->request_key,
            $product->PaymentProfile->provider_id,
            $transactionId,
            'payment',
            "[{$platform}] Received in-app purchase",
            array_merge([
                '_POST' => $_POST,
                'store_product_id' => $product->store_product_id,
            ], $data),
            $subscriberId
        );

        $state = new XF\Payment\CallbackState();
        $state->purchaseRequest = $purchaseRequest;
        $state->paymentResult = XF\Payment\CallbackState::PAYMENT_RECEIVED;
        $state->transactionId = $transactionId;
        $state->subscriberId = $subscriberId;
        $handler->completeTransaction($state);

        $handler->log($state);

        return $this->apiSuccess([
            'message' => XF::phrase('tapi_your_account_has_been_upgraded'),
            'request_key' => $purchaseRequest->request_key,
        ]);
    }

    protected function decryptPassword(string $encryptedPassword): string
    {
        if ($this->request()->exists('password_algo')) {
            $algo = $this->filter('password_algo', 'str');
        } else {
            $algo = Encryption::ALGO_AES_256_CBC;
        }

        if (Encryption::isSupportedAlgo($algo)) {
            try {
                $password = Encryption::decrypt($encryptedPassword, $this->options()->tApi_encryptKey, $algo);
            } catch (Throwable $e) {
                throw $this->exception($this->error(XF::phrase('incorrect_password')));
            }
        } else {
            $password = $encryptedPassword;
        }

        return $password;
    }

    protected function createExternalAccount(
        XF\ConnectedAccount\ProviderData\AbstractProviderData $providerData,
        ConnectedAccountProvider $provider,
        array $input
    ): XF\Entity\User {
        if (!$this->options()->registrationSetup['enabled']) {
            throw $this->exception($this->error(XF::phrase('new_registrations_currently_not_being_accepted'), 400));
        }

        $filterer = $this->app->inputFilterer();

        if ($providerData->email) {
            /** @var \XF\Entity\User|null $emailUser */
            $emailUser = $this->finder('XF:User')->where('email', $providerData->email)->fetchOne();
            if ($emailUser !== null && $emailUser->user_id !== XF::visitor()->user_id) {
                throw $this->exception($this->error(XF::phrase('this_accounts_email_is_already_associated_with_another_member', [
                    'provider' => $provider->title,
                    'boardTitle' => $this->options()->boardTitle,
                ])));
            }

            $input['email'] = $filterer->cleanString($providerData->email);
        }

        $options = $this->options();

        if ($providerData->dob) {
            $dob = $providerData->dob;
            $input['dob_day'] = $dob['dob_day'];
            $input['dob_month'] = $dob['dob_month'];
            $input['dob_year'] = $dob['dob_year'];
        } else {
            $options->offsetSet('registrationSetup', array_replace($options->registrationSetup, [
                'requireDob' => false,
            ]));
        }

        $options->offsetSet('registrationSetup', array_replace($options->registrationSetup, [
            'requireLocation' => false,
        ]));

        /** @var \XF\Service\User\Registration $registration */
        $registration = $this->service('XF:User\Registration');
        if (strlen($input['password']) === 0) {
            // to support old version
            $registration->setNoPassword();
            unset($input['password']);
        }

        $registration->setFromInput($input);

        if ($providerData->email) {
            $registration->skipEmailConfirmation();
        }

        $avatarUrl = $providerData->avatar_url ?? null;
        // @phpstan-ignore-next-line
        if ($avatarUrl) {
            $registration->setAvatarUrl($avatarUrl);
        }

        if (!$registration->validate($errors)) {
            throw $this->exception($this->error($errors));
        }

        /** @var User $user */
        $user = $registration->save();

        return $user;
    }

    protected function getUserApiResultOptions(XF\Entity\User $user): array
    {
        return [
            'tapi_permissions' => [
                'username' => true,
            ],
            'tapi_user_state_message' => true,
        ];
    }

    protected function getAuthResultData(\XF\Entity\User $user, bool $withRefreshToken = true): array
    {
        /** @var \Truonglv\Api\Repository\Token $tokenRepo */
        $tokenRepo = XF::repository('Truonglv\Api:Token');

        $data = [
            'user' => $user->toApiResult(Entity::VERBOSITY_VERBOSE, $this->getUserApiResultOptions($user)),
            'accessToken' => $tokenRepo->createAccessToken($user->user_id, $this->options()->tApi_accessTokenTtl),
        ];

        /** @var AccessToken $token */
        $token = $this->em()->find('Truonglv\Api:AccessToken', $data['accessToken']);
        $data['expiresAt'] = $token->expire_date;

        if ($withRefreshToken) {
            $data['refreshToken'] = $tokenRepo->createRefreshToken($user->user_id, 30 * 86400);
        }

        return $data;
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
            'REQUEST_METHOD' => strtoupper($job['method'])
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
            throw $this->errorException(XF::phrase('your_account_has_temporarily_been_locked_due_to_failed_login_attempts'), 400);
        }

        /** @var \XF\Entity\User|null $user */
        $user = $loginService->validate($password, $error);
        if ($user === null) {
            throw $this->errorException($error, 400);
        }

        if (!$this->runTfaValidation($user)) {
            throw $this->errorException(XF::phrase('two_step_verification_value_could_not_be_confirmed'));
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
            throw $this->exception($this->message(XF::phrase('two_step_verification_required'), 202));
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
            throw $this->errorException(XF::phrase('your_account_has_temporarily_been_locked_due_to_failed_login_attempts'));
        }

        if (!$tfaService->verify($this->request(), $provider)) {
            throw $this->errorException(XF::phrase('two_step_verification_value_could_not_be_confirmed'));
        }

        return true;
    }

    /** @noinspection PhpUnused */
    public function actionPostRefreshToken()
    {
        $this->assertRequiredApiInput(['token']);

        /** @var RefreshToken|null $token */
        $token = $this->finder('Truonglv\Api:RefreshToken')
            ->with('User', true)
            ->whereId($this->filter('token', 'str'))
            ->fetchOne();
        if ($token === null) {
            return $this->notFound();
        }

        if ($token->isExpired()) {
            return $this->noPermission();
        }

        $token->expire_date = \time() + 30 * 86400;
        $token->save();

        /** @var \XF\Entity\User $user */
        $user = $token->User;

        return $this->apiSuccess($this->getAuthResultData($user, false));
    }

    /**
     * @return array
     */
    protected function getAppInfo(): array
    {
        /** @var \Truonglv\Api\Api\ControllerPlugin\App $appPlugin */
        $appPlugin = $this->plugin('Truonglv\Api:Api:App');

        return $appPlugin->getAppInfo();
    }

    protected function getNewsFeedsFilters(): array
    {
        $input = $this->filter([
            'order' => 'str',
            'direction' => 'str',
            'unread' => 'bool',
            'watched' => 'bool',
        ]);

        $filters = [
            'order' => 'last_post_date',
            'direction' => 'desc',
        ];
        if (in_array($input['direction'], ['asc', 'desc'], true)) {
            $filters['direction'] = $input['direction'];
        }

        $allowedOrders = [
            'post_date',
            'last_post_date',
            'reply_count',
            'view_count',
        ];
        if (in_array($input['order'], $allowedOrders, true)) {
            $filters['order'] = $input['order'];
        }

        if ($input['unread'] === true) {
            $filters['order'] = 'last_post_date';
            $filters['direction'] = 'desc';
            $filters['unread'] = true;
        }
        if ($input['watched'] === true) {
            $filters['order'] = 'last_post_date';
            $filters['direction'] = 'desc';
            $filters['watched'] = true;
        }

        return $filters;
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

        $forumIds = $this->getViewableNodeIds();
        if (count($forumIds) > 0) {
            $finder->where('node_id', $forumIds);
        } else {
            $finder->whereImpossible();
        }

        /** @var \XF\Repository\Thread $threadRepo */
        $threadRepo = $this->repository('XF:Thread');
        $readMarkingCutoff = $threadRepo->getReadMarkingCutOff();

        switch ($filters['order']) {
            case 'last_post_date':
                $finder->order('last_post_date', $filters['direction']);
                $finder->where('last_post_date', '>=', $readMarkingCutoff);

                break;
            case 'reply_count':
                $finder->order('reply_count', $filters['direction']);
                $finder->where('post_date', '>=', $readMarkingCutoff);

                break;
            case 'post_date':
                $finder->order('post_date', $filters['direction']);
                $finder->where('post_date', '>=', $readMarkingCutoff);

                break;
            case 'view_count':
                $finder->order('view_count', $filters['direction']);
                $finder->where('post_date', '>=', $readMarkingCutoff);

                break;
            default:
                throw new LogicException('Unsupported news feeds order: ' . $filters['order']);
        }

        if (isset($filters['unread'])) {
            $finder->unreadOnly();
        }
        if (isset($filters['watched'])) {
            $finder->watchedOnly();
        }
    }

    protected function runNewsFeedSearch(string $searchQuery): ?\XF\Entity\Search
    {
        $visitor = XF::visitor();
        $filters = $this->getNewsFeedsFilters();

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

        if (count($searchResults) === 0) {
            return null;
        }

        /** @var \XF\Entity\Search $search */
        $search = $this->em()->create('XF:Search');
        $search->search_type = 'thread';
        $search->search_query = $searchQuery;
        $search->query_hash = md5(__METHOD__ . $searchQuery . json_encode($filters));
        $search->search_results = $searchResults;
        $search->result_count = count($searchResults);
        $search->search_date = XF::$time;
        $search->user_id = $visitor->user_id;
        $search->search_order = 'date';
        $search->search_grouping = true;
        $search->search_constraints = [];

        $search->save();

        return $search;
    }

    protected function getViewableNodeIds(): array
    {
        /** @var Node $nodeRepo */
        $nodeRepo = $this->repository('XF:Node');
        $nodes = $nodeRepo->getNodeList();

        $nodeIds = [];
        /** @var \XF\Entity\Node $node */
        foreach ($nodes as $node) {
            $nodeIds[] = $node->node_id;
        }

        return $nodeIds;
    }
}
