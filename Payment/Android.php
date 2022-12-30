<?php

namespace Truonglv\Api\Payment;

use XF;
use Throwable;
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
use XF\Entity\PaymentProfile;
use XF\Payment\CallbackState;
use XF\Entity\PurchaseRequest;
use XF\Payment\AbstractProvider;
use Truonglv\Api\Entity\IAPProduct;
use Google\Service\AndroidPublisher;

class Android extends AbstractProvider implements IAPInterface
{
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
            'service_account_json' => ''
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
        $state->inputRaw = $inputRaw;

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

        $state->inputFiltered = $filtered;

        /** @var IAPProduct|null $product */
        $product = XF::finder('Truonglv\Api:IAPProduct')
            ->where('platform', 'android')
            ->where('store_product_id', $filtered['subscriptionNotification']['subscriptionId'])
            ->fetchOne();
        if ($product === null) {
            $state->logType = 'error';
            $state->logMessage = 'No iap product';

            return $state;
        }

        if ($filtered['packageName'] !== $product->PaymentProfile->options['app_bundle_id']) {
            $state->logType = 'error';
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

        $state->androidPurchase = $purchase;
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
            $logFinder = XF::finder('XF:PaymentProviderLog')
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
            $this->ackPurchase(
                $service,
                $filtered['packageName'],
                $filtered['subscriptionNotification']['subscriptionId'],
                $filtered['subscriptionNotification']['purchaseToken'],
                [
                    'request_key' => $state->requestKey,
                ]
            );
        }

        $state->ip = $request->getIp();
        $state->_POST = $_POST;

        return $state;
    }

    /**
     * @param CallbackState $state
     * @return bool
     */
    public function validateTransaction(CallbackState $state)
    {
        /** @var XF\Repository\Payment $paymentRepo */
        $paymentRepo = XF::repository('XF:Payment');
        if (isset($state->androidPurchase)) {
            /** @var AndroidPublisher\SubscriptionPurchase $purchase */
            $purchase = $state->androidPurchase;
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

    /**
     * @param CallbackState $state
     * @return void
     */
    public function getPaymentResult(CallbackState $state)
    {
        if (isset($state->androidPurchase)) {
            /** @var AndroidPublisher\SubscriptionPurchase $purchase */
            $purchase = $state->androidPurchase;

            if ($this->isPurchaseCancelled($purchase)) {
                $state->logType = 'cancel';
                $state->paymentResult = CallbackState::PAYMENT_REVERSED;
            } elseif ($this->isPurchaseReceived($purchase)) {
                $state->paymentResult = CallbackState::PAYMENT_RECEIVED;
            }
        }
    }

    protected function isPurchaseCancelled(AndroidPublisher\SubscriptionPurchase $purchase): bool
    {
        /** @var mixed $cancelReason */
        $cancelReason = $purchase->getCancelReason();

        return $cancelReason !== null && $cancelReason >= 0;
    }

    protected function isPurchaseReceived(AndroidPublisher\SubscriptionPurchase $purchase): bool
    {
        $state = $purchase->getPaymentState();

        return $state === 1;
    }

    /**
     * @param CallbackState $state
     * @return void
     */
    public function prepareLogData(CallbackState $state)
    {
        $logDetails = [];

        $logDetails['inputRaw'] = $state->inputRaw;
        $logDetails['inputFiltered'] = $state->inputFiltered;

        if (isset($state->androidPurchase)) {
            /** @var AndroidPublisher\SubscriptionPurchase $purchase */
            $purchase = $state->androidPurchase;
            $logDetails['purchase'] = $purchase->toSimpleObject();
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
        ];
        $paymentLog->purchase_request_key = $purchaseRequest->request_key;
        $paymentLog->provider_id = $this->getProviderId();
        $paymentLog->save();

        $expires = $purchase->getExpiryTimeMillis() / 1000;
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

    protected function ackPurchase(AndroidPublisher $publisher, string $packageName, string $subId, string $token, array $devPayload = []): void
    {
        $ackBody = new AndroidPublisher\SubscriptionPurchasesAcknowledgeRequest();
        $ackBody->setDeveloperPayload(\GuzzleHttp\json_encode($devPayload));

        $publisher->purchases_subscriptions->acknowledge(
            $packageName,
            $subId,
            $token,
            $ackBody
        );
    }
}
