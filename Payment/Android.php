<?php

namespace Truonglv\Api\Payment;

use Truonglv\Api\Finder\IAPProductFinder;
use XF;
use Throwable;
use function ceil;
use function time;
use function trim;
use Google\Client;
use LogicException;
use XF\Mvc\Controller;
use function preg_match;
use function json_decode;
use function array_replace;
use function base64_decode;
use XF\Purchasable\Purchase;
use Google\Service\Exception;
use XF\Entity\PaymentProfile;
use XF\Payment\CallbackState;
use function array_key_exists;
use XF\Entity\PurchaseRequest;
use XF\Payment\AbstractProvider;
use Truonglv\Api\Entity\IAPProduct;
use Google\Service\AndroidPublisher;

class Android extends AbstractProvider implements IAPInterface
{
    const KEY_STATE_ANDROID_PURCHASE = 'androidPurchase';
    const KEY_STATE_DATA_RAW = 'dataRaw';
    const KEY_INPUT_FILTERED = 'inputFiltered';
    const KEY_INPUT_RAW = 'inputRaw';

    /**
     * @return string
     */
    public function getTitle()
    {
        return '[tl] Api: In-app purchase Android';
    }

    /**
     * @param Controller $controller
     * @param PurchaseRequest $purchaseRequest
     * @param Purchase $purchase
     * @return mixed
     */
    public function initiatePayment(Controller $controller, PurchaseRequest $purchaseRequest, Purchase $purchase)
    {
        throw new LogicException('Not supported');
    }

    /**
     * @param array $options
     * @param mixed $errors
     * @return bool
     */
    public function verifyConfig(array &$options, &$errors = [])
    {
        $options = array_replace([
            'app_bundle_id' => '',
            'service_account_json' => '',
            'expires_extra_seconds' => 120,
        ], $options);

        if (strlen($options['app_bundle_id']) === 0) {
            $errors[] = XF::phrase('tapi_iap_ios_please_enter_valid_app_bundle_id');

            return false;
        }

        try {
            \GuzzleHttp\json_decode($options['service_account_json']);
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();

            return false;
        }

        return true;
    }

    /**
     * @param Controller $controller
     * @param PurchaseRequest $purchaseRequest
     * @param PaymentProfile $paymentProfile
     * @param Purchase $purchase
     * @return mixed
     */
    public function processPayment(Controller $controller, PurchaseRequest $purchaseRequest, PaymentProfile $paymentProfile, Purchase $purchase)
    {
        throw new LogicException('Not supported');
    }

    public function setupCallback(\XF\Http\Request $request)
    {
        $inputRaw = trim($request->getInputRaw());
        $state = new CallbackState();
        $state->{static::KEY_INPUT_RAW} = $inputRaw;

        $json = (array) json_decode($inputRaw, true);
        if (!isset($json['message'])) {
            $state->logType = 'error';
            $state->logMessage = 'Invalid payload. No `message`';

            return $state;
        }

        $data = (array) json_decode(base64_decode($json['message']['data'], true), true);

        $filtered = $request->getInputFilterer()->filterArray($data, [
            'version' => 'str',
            'packageName' => 'str',
            'eventTimeMillis' => 'uint',
            'subscriptionNotification' => [
                'version' => 'str',
                'notificationType' => 'int',
                'purchaseToken' => 'str',
                'subscriptionId' => 'str'
            ],
        ]);

        $state->{static::KEY_INPUT_FILTERED} = $filtered;
        $state->{static::KEY_STATE_DATA_RAW} = $json;

        /** @var IAPProduct|null $product */
        $product = XF::finder(IAPProductFinder::class)
            ->where('platform', 'android')
            ->where('store_product_id', $filtered['subscriptionNotification']['subscriptionId'])
            ->fetchOne();
        if ($product === null) {
            $state->logType = 'info';
            $state->logMessage = 'No iap product';

            return $state;
        }

        if ($filtered['packageName'] !== $product->PaymentProfile->options['app_bundle_id']) {
            $state->logType = 'info';
            $state->logMessage = 'Invalid app bundle ID';

            return $state;
        }

        $service = $this->getAndroidPublisher($product->PaymentProfile);

        try {
            $purchase = $service->purchases_subscriptions->get(
                $filtered['packageName'],
                $filtered['subscriptionNotification']['subscriptionId'],
                $filtered['subscriptionNotification']['purchaseToken']
            );
        } catch (Throwable $e) {
            $state->logType = 'error';
            $state->logMessage = 'Get purchase subscription error: ' . $e->getMessage();

            return $state;
        }

        $state->{static::KEY_STATE_ANDROID_PURCHASE} = $purchase;
        $transInfo = $this->getIAPTransactionInfo($purchase);
        if ($transInfo === null) {
            $state->logType = 'error';
            $state->logMessage = 'No orderId';

            return $state;
        }

        $state->subscriberId = $transInfo['subscriber_id'];
        $state->transactionId = $transInfo['transaction_id'];

        /** @var PurchaseRequest|null $purchaseRequest */
        $purchaseRequest = XF::em()->findOne(
            'XF:PurchaseRequest',
            ['provider_metadata' => $transInfo['subscriber_id']]
        );

        if ($purchaseRequest !== null) {
            $state->purchaseRequest = $purchaseRequest; // sets requestKey too
        } else {
            $logFinder = XF::finder(XF\Finder\PaymentProviderLogFinder::class)
                ->where('subscriber_id', $transInfo['subscriber_id'])
                ->where('provider_id', $this->providerId)
                ->order('log_date', 'desc');

            foreach ($logFinder->fetch() as $log) {
                if ($log->purchase_request_key) {
                    $state->requestKey = $log->purchase_request_key;

                    break;
                }
            }
        }

        if ($purchase->getAcknowledgementState() === 0) {
            try {
                $this->ackPurchase(
                    $service,
                    $filtered['packageName'],
                    $filtered['subscriptionNotification']['subscriptionId'],
                    $filtered['subscriptionNotification']['purchaseToken'],
                    [
                        'request_key' => $state->requestKey,
                    ]
                );
            } catch (Throwable $e) {
                $state->logType = 'error';
                $state->logMessage = 'failed to ack purchase';

                return $state;
            }
        }

        $state->ip = $request->getIp();
        $state->_POST = $_POST;

        return $state;
    }

    /**
     * @param CallbackState $state
     * @return bool
     */
    public function validateCallback(CallbackState $state)
    {
        if ($this->isEventSkippable($state)) {
            $state->httpCode = 200;

            return false;
        }

        return parent::validateCallback($state);
    }

    protected function isEventSkippable(CallbackState $state): bool
    {
        $requestKey = $state->requestKey;
        $purchase = $this->getSubscriptionPurchase($state);
        $dataRaw = $state->{self::KEY_STATE_DATA_RAW} ?? [];

        if ($purchase !== null && $requestKey === null) {
            $payload = (array) json_decode($purchase->getDeveloperPayload(), true);
            if (!array_key_exists('request_key', $payload) || $payload['request_key'] === null) {
                $state->logMessage = 'Invalid purchase request';
                $lastLog = XF::finder(XF\Finder\PaymentProviderLogFinder::class)
                    ->where('subscriber_id', $state->subscriberId)
                    ->where('provider_id', $this->providerId)
                    ->where('log_type', 'info')
                    ->where('log_message', $state->logMessage)
                    ->order('log_date', 'desc')
                    ->fetchOne();
                $state->logType = $lastLog === null ? 'info' : '';

                return true;
            }
        } elseif (isset($dataRaw['deliveryAttempt']) && $dataRaw['deliveryAttempt'] >= 5) {
            $state->logType = 'info';
            $state->logMessage = 'Too many delivery attempts';

            return true;
        }

        return false;
    }

    /**
     * @param CallbackState $state
     * @return bool
     */
    public function validateTransaction(CallbackState $state)
    {
        $paymentRepo = XF::repository(XF\Repository\PaymentRepository::class);
        $purchase = $this->getSubscriptionPurchase($state);
        if ($purchase !== null) {
            $total = null;

            if ($this->isPurchaseCancelled($purchase)) {
                $total = $paymentRepo->findLogsByTransactionIdForProvider(
                    $state->transactionId,
                    $this->providerId,
                    ['cancel']
                )->total();
            } elseif ($this->isPurchaseReceived($purchase)) {
                $total = $paymentRepo->findLogsByTransactionIdForProvider(
                    $state->transactionId,
                    $this->providerId,
                    ['payment']
                )->total();
            }

            if ($total !== null) {
                if ($total > 0) {
                    $state->logType = 'info';
                    $state->logMessage = 'Transaction already processed. Skipping.';

                    return false;
                }

                return true;
            }
        }

        return parent::validateTransaction($state);
    }

    protected function getSubscriptionPurchase(CallbackState $state): ?AndroidPublisher\SubscriptionPurchase
    {
        return $state->{static::KEY_STATE_ANDROID_PURCHASE};
    }

    /**
     * @param CallbackState $state
     * @link https://developers.google.com/android-publisher/api-ref/rest/v3/purchases.subscriptions#SubscriptionPurchase
     * @return void
     */
    public function getPaymentResult(CallbackState $state)
    {
        $purchase = $this->getSubscriptionPurchase($state);
        if ($purchase !== null) {
            if ($this->isPurchaseCancelled($purchase)) {
                $state->logType = 'cancel';
            } elseif ($this->isPurchaseReceived($purchase)) {
                $state->paymentResult = CallbackState::PAYMENT_RECEIVED;
            }
        }
    }

    protected function isPurchaseCancelled(AndroidPublisher\SubscriptionPurchase $purchase): bool
    {
        /** @var mixed $cancelReason */
        $cancelReason = $purchase->getCancelReason();

        // 0-user canceled the subscription
        // 1-canceled by the system
        // 2-replaced by new subscription
        // 3-canceled by developer
        return $cancelReason !== null && $cancelReason >= 0;
    }

    protected function isPurchaseReceived(AndroidPublisher\SubscriptionPurchase $purchase): bool
    {
        $state = $purchase->getPaymentState();

        // 0-payment pending
        // 1-payment received
        // 2-free trial
        // 3-pending deferred upgrade/downgrade
        return $state === 1;
    }

    /**
     * @param CallbackState $state
     * @return void
     */
    public function prepareLogData(CallbackState $state)
    {
        $logDetails = [];

        $logDetails[static::KEY_INPUT_RAW] = $state->{static::KEY_INPUT_RAW};
        $logDetails[static::KEY_INPUT_FILTERED] = $state->{static::KEY_INPUT_FILTERED};
        $logDetails[static::KEY_STATE_DATA_RAW] = $state->{static::KEY_STATE_DATA_RAW};

        $purchase = $this->getSubscriptionPurchase($state);
        if ($purchase !== null) {
            $logDetails[static::KEY_STATE_ANDROID_PURCHASE] = $purchase->toSimpleObject();
        }

        $state->logDetails = $logDetails;
    }

    public function getAndroidPublisher(PaymentProfile $paymentProfile): AndroidPublisher
    {
        $client = new Client();
        $serviceAccount = \GuzzleHttp\json_decode($paymentProfile->options['service_account_json'], true);

        $client->setAuthConfig($serviceAccount);
        $client->addScope('https://www.googleapis.com/auth/androidpublisher');

        return new AndroidPublisher($client);
    }

    protected function getIAPTransactionInfo(AndroidPublisher\SubscriptionPurchase $purchase): ?array
    {
        $transactionId = $purchase->getOrderId();
        if (\strlen($transactionId) === 0) {
            return null;
        }

        return $this->getTransactionInfoFromId($transactionId);
    }

    public function getTransactionInfoFromId(string $transactionId): array
    {
        if (preg_match('#(.*)\.{2}(\d+)$#', $transactionId, $matches) === 1) {
            $subscriberId = $matches[1];
        } else {
            $subscriberId = $transactionId;
        }

        return [
            'transaction_id' => $transactionId,
            'subscriber_id' => $subscriberId,
        ];
    }

    public function verifyIAPTransaction(PurchaseRequest $purchaseRequest, array $payload): array
    {
        $knownTransactionId = $payload['purchase']['transactionId'] ?? null;
        if ($knownTransactionId !== null) {
            $info = $this->getTransactionInfoFromId($knownTransactionId);
            // quickly update request sub info to prevent get many errors of
            // unknown purchase request key
            $purchaseRequest->fastUpdate('provider_metadata', $info['subscriber_id']);
        }

        $paymentProfile = $purchaseRequest->PaymentProfile;
        if ($paymentProfile->options['app_bundle_id'] !== $payload['package_name']) {
            throw new LogicException('Invalid bundle.');
        }

        $service = $this->getAndroidPublisher($paymentProfile);
        $purchase = $service->purchases_subscriptions->get(
            $payload['package_name'],
            $payload['subscription_id'],
            $payload['token']
        );

        /** @var \XF\Entity\PaymentProviderLog $paymentLog */
        $paymentLog = XF::em()->create('XF:PaymentProviderLog');
        $paymentLog->log_type = 'info';
        $paymentLog->log_message = '[Android] Verify receipt response';
        $paymentLog->log_details = [
            'payload' => $payload,
            'response' => $purchase->toSimpleObject(),
            '_POST' => $_POST,
            'store_product_id' => $purchaseRequest->extra_data['store_product_id'],
        ];
        $paymentLog->purchase_request_key = $purchaseRequest->request_key;
        $paymentLog->provider_id = $this->getProviderId();
        $paymentLog->save();

        $expires = ceil($purchase->getExpiryTimeMillis() / 1000) + $this->getPurchaseExpiresExtraSeconds($purchaseRequest->PaymentProfile);
        if ($expires <= time()) {
            throw new PurchaseExpiredException();
        }

        // https://developers.google.com/android-publisher/api-ref/rest/v3/purchases.subscriptions#SubscriptionPurchase
        $transInfo = $this->getIAPTransactionInfo($purchase);
        if ($transInfo !== null && $this->isPurchaseReceived($purchase)) {
            $paymentLog->fastUpdate([
                'transaction_id' => $transInfo['transaction_id'],
                'subscriber_id' => $transInfo['subscriber_id'],
            ]);

            // ack
            if ($purchase->getAcknowledgementState() === 0) {
                $this->ackPurchase($service, $payload['package_name'], $payload['subscription_id'], $payload['token'], [
                    'user_id' => $purchaseRequest->user_id,
                    'request_key' => $purchaseRequest->request_key,
                ]);
            }

            return $transInfo;
        }

        $_POST['android_purchase'] = $purchase->toSimpleObject();

        throw new LogicException('Cannot verify transaction');
    }

    protected function getPurchaseExpiresExtraSeconds(PaymentProfile $paymentProfile): int
    {
        return $paymentProfile->options['expires_extra_seconds'] ?? 120;
    }

    protected function ackPurchase(AndroidPublisher $publisher, string $packageName, string $subId, string $token, array $devPayload = []): void
    {
        $ackBody = new AndroidPublisher\SubscriptionPurchasesAcknowledgeRequest();
        $ackBody->setDeveloperPayload(\GuzzleHttp\json_encode($devPayload));

        try {
            $publisher->purchases_subscriptions->acknowledge(
                $packageName,
                $subId,
                $token,
                $ackBody
            );
        } catch (Throwable $e) {
            if ($e instanceof Exception) {
                $message = (array) json_decode($e->getMessage(), true);
                if (isset($message['error'], $message['error']['errors'])) {
                    $reason = $message['error']['errors'][0]['reason'];
                    if ($reason === 'alreadyAcknowledged') {
                        // skip
                        return;
                    }
                }
            }

            throw $e;
        }
    }
}
