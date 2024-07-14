<?php

namespace Truonglv\Api\Payment;

use XF;
use Throwable;
use function time;
use function count;
use LogicException;
use function strlen;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use function explode;
use function stripos;
use function in_array;
use XF\Mvc\Controller;
use function json_decode;
use function array_replace;
use function base64_decode;
use XF\Purchasable\Purchase;
use InvalidArgumentException;
use XF\Entity\PaymentProfile;
use XF\Payment\CallbackState;
use XF\Entity\PurchaseRequest;
use function file_get_contents;
use function openssl_x509_read;
use XF\Payment\AbstractProvider;
use function openssl_x509_verify;
use Truonglv\Api\Entity\IAPProduct;
use function openssl_pkey_get_public;
use function openssl_pkey_get_details;

class IOS extends AbstractProvider implements IAPInterface
{
    const NOTIFICATION_TYPE_SUBSCRIBED = 'SUBSCRIBED';
    const NOTIFICATION_TYPE_DID_RENEW = 'DID_RENEW';
    const NOTIFICATION_TYPE_EXPIRED = 'EXPIRED';
    const NOTIFICATION_TYPE_REFUND = 'REFUND';

    /**
     * @var bool
     */
    protected $forceSandbox = false;

    /**
     * @return string
     */
    public function getTitle()
    {
        return '[tl] Api: In-app purchase IOS';
    }

    /**
     * @param array $options
     * @param mixed $errors
     * @return bool
     */
    public function verifyConfig(array &$options, &$errors = [])
    {
        $options = array_replace([
            'app_shared_pass' => '',
            'app_bundle_id' => '',
            'expires_extra_seconds' => 120,
        ], $options);
        if (strlen($options['app_shared_pass']) === 0) {
            $errors[] = XF::phrase('tapi_iap_ios_please_enter_valid_app_shared_pass');

            return false;
        }

        if (strlen($options['app_bundle_id']) === 0) {
            $errors[] = XF::phrase('tapi_iap_ios_please_enter_valid_app_bundle_id');

            return false;
        }

        return true;
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
     * @param Controller $controller
     * @param PurchaseRequest $purchaseRequest
     * @param PaymentProfile $paymentProfile
     * @param Purchase $purchase
     * @return null
     */
    public function processPayment(Controller $controller, PurchaseRequest $purchaseRequest, PaymentProfile $paymentProfile, Purchase $purchase)
    {
        throw new LogicException('Not supported');
    }

    /**
     * @param \XF\Http\Request $request
     * @return CallbackState
     */
    public function setupCallback(\XF\Http\Request $request)
    {
        $inputRaw = $request->getInputRaw();
        $json = \GuzzleHttp\json_decode($inputRaw, true);
        $signedPayload = $json['signedPayload'] ?? '';

        $state = new CallbackState();
        $state->inputRaw = $inputRaw;

        if (stripos($signedPayload, '.') === false) {
            return $state;
        }

        $parts = explode('.', $signedPayload, 3);
        if (count($parts) !== 3) {
            $state->logType = 'error';
            $state->logMessage = 'Invalid JWT token';

            return $state;
        }

        list($head64, $body64, ) = $parts;
        $header = \GuzzleHttp\json_decode(base64_decode($head64, true));

        if (!isset($header->x5c) || count($header->x5c) !== 3 || !isset($header->alg)) {
            $state->logType = 'error';
            $state->logMessage = 'Invalid JWT token';

            return $state;
        }

        $certificate = $this->getCertificate($header->x5c[0]);
        $intermediateCertificate = $this->getCertificate($header->x5c[1]);
        $rootCertificate = $this->getCertificate($header->x5c[2]);

        $appleRootCa = file_get_contents(
            XF::getAddOnDirectory() . '/Truonglv/Api/AppleRootCA-g3.pem'
        );
        if (openssl_x509_verify($rootCertificate, $appleRootCa) !== 1
            || openssl_x509_verify($intermediateCertificate, $rootCertificate) !== 1
        ) {
            $state->logType = 'error';
            $state->logMessage = 'Certificate mismatch!';

            return $state;
        }

        try {
            $certObj = openssl_x509_read($certificate);
            $pkeyObj = openssl_pkey_get_public($certObj);
            $pkeyArr = openssl_pkey_get_details($pkeyObj);

            $data = \GuzzleHttp\json_decode(base64_decode($body64, true));
            $transaction = JWT::decode($data->data->signedTransactionInfo, new Key($pkeyArr['key'], $header->alg));
            $signedRenewableInfo = JWT::decode($data->data->signedRenewalInfo, new Key($pkeyArr['key'], $header->alg));
        } catch (Throwable $e) {
            $state->logType = 'error';
            $state->logMessage = 'Decode JWT error: ' . $e->getMessage();

            return $state;
        }

        $originalTransactionId = $transaction->originalTransactionId;
        $transactionId = $transaction->transactionId;

        $state->signedTransaction = $transaction;
        $state->signedRenewable = $signedRenewableInfo;
        $state->notificationType = $data->notificationType;

        $state->subscriberId = $originalTransactionId;
        $state->transactionId = $transactionId;

        $storeProductId = $transaction->productId;
        /** @var IAPProduct|null $iapProduct */
        $iapProduct = XF::em()->findOne('Truonglv\Api:IAPProduct', [
            'platform' => 'ios',
            'store_product_id' => $storeProductId
        ]);
        if ($iapProduct === null) {
            $state->logType = 'info';
            $state->logMessage = 'No IAP product';

            return $state;
        }

        $state->ip = $request->getIp();
        $state->_POST = $_POST;

        // setup from subscription
        if ($originalTransactionId) {
            $purchaseRequests = XF::finder('XF:PurchaseRequest')
                ->where('provider_id', $this->providerId)
                ->where('provider_metadata', $originalTransactionId)
                ->order('purchase_request_id', 'desc')
                ->fetch();
            /** @var PurchaseRequest|null $purchaseRequest */
            $purchaseRequest = null;

            /** @var PurchaseRequest $_purchaseRequest */
            foreach ($purchaseRequests as $_purchaseRequest) {
                if ($this->validateIAPPurchaseRequest($_purchaseRequest, $storeProductId)) {
                    $purchaseRequest = $_purchaseRequest;

                    break;
                }
            }

            if ($purchaseRequest !== null) {
                $state->purchaseRequest = $purchaseRequest; // sets requestKey too
            } else {
                $logFinder = XF::finder('XF:PaymentProviderLog')
                    ->where('subscriber_id', $originalTransactionId)
                    ->where('provider_id', $this->providerId)
                    ->order('log_date', 'desc');

                /** @var XF\Entity\PaymentProviderLog $log */
                foreach ($logFinder->fetch() as $log) {
                    if ($this->getLoggedProductId($log) === $storeProductId
                        && $log->PurchaseRequest !== null
                        && $this->validateIAPPurchaseRequest($log->PurchaseRequest, $storeProductId)
                    ) {
                        $state->purchaseRequest = $log->PurchaseRequest;

                        break;
                    }
                }
            }
        }

        return $state;
    }

    protected function getLoggedProductId(XF\Entity\PaymentProviderLog $log): string
    {
        $loggedProductId = $log->log_details['signedTransaction']['productId'] ?? '';
        if ($loggedProductId === '') {
            $purchase = (array) json_decode($log->log_details['_POST']['purchase'] ?? '', true);
            $loggedProductId = $purchase['productId'] ?? '';
        }

        return $loggedProductId;
    }

    protected function validateIAPPurchaseRequest(PurchaseRequest $purchaseRequest, string $storeProductId): bool
    {
        if (isset($purchaseRequest->extra_data['store_product_id'])
            && $purchaseRequest->extra_data['store_product_id'] === $storeProductId
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param CallbackState $state
     * @return bool
     */
    public function validateCallback(CallbackState $state)
    {
        if (!isset($state->signedTransaction)) {
            $state->logType = 'error';
            $state->logMessage = 'Unknown transaction.';

            return false;
        }

        /** @var PurchaseRequest|null $purchaseRequest */
        $purchaseRequest = $state->getPurchaseRequest();
        $extraSeconds = 0;
        if ($purchaseRequest !== null) {
            $extraSeconds = $this->getPurchaseExpiresExtraSeconds($purchaseRequest->PaymentProfile);
        }

        $expiresDate = ceil($state->signedTransaction->expiresDate / 1000) + $extraSeconds;
        if ($expiresDate <= time()) {
            $state->logType = 'error';
            $state->logMessage = 'Transaction was expired.';

            return false;
        }

        return true;
    }

    /**
     * @param CallbackState $state
     * @return bool
     */
    public function validateTransaction(CallbackState $state)
    {
        if (isset($state->notificationType)
            && in_array($state->notificationType, [static::NOTIFICATION_TYPE_EXPIRED, static::NOTIFICATION_TYPE_REFUND], true)
        ) {
            /** @var XF\Repository\Payment $paymentRepo */
            $paymentRepo = XF::repository('XF:Payment');
            $total = $paymentRepo->findLogsByTransactionIdForProvider(
                $state->transactionId,
                $this->providerId,
                ['cancel']
            )->total();
            if ($total > 0) {
                $state->logType = 'info';
                $state->logMessage = 'Transaction already processed. Skipping.';

                return false;
            }

            return true;
        }

        return parent::validateTransaction($state);
    }

    /**
     * @param CallbackState $state
     * @return void
     */
    public function getPaymentResult(CallbackState $state)
    {
        if ($state->notificationType === static::NOTIFICATION_TYPE_SUBSCRIBED
            || $state->notificationType === static::NOTIFICATION_TYPE_DID_RENEW
        ) {
            $state->paymentResult = CallbackState::PAYMENT_RECEIVED;
        } elseif ($state->notificationType === static::NOTIFICATION_TYPE_REFUND) {
            $state->paymentResult = CallbackState::PAYMENT_REVERSED;
        }
    }

    /**
     * @return string
     */
    public function getApiEndpoint()
    {
        $enableLivePayments = (bool) XF::config('enableLivePayments');
        if (!$enableLivePayments ||  $this->forceSandbox) {
            return 'https://sandbox.itunes.apple.com';
        }

        return 'https://buy.itunes.apple.com';
    }

    /**
     * @param CallbackState $state
     * @return void
     */
    public function prepareLogData(CallbackState $state)
    {
        $logDetails = [];
        if (isset($state->apiLogDetails)) {
            $logDetails = $state->apiLogDetails;
        }

        $logDetails['signedTransaction'] = $state->signedTransaction;
        $logDetails['signedRenewable'] = $state->signedRenewable;
        $logDetails['notificationType'] = $state->notificationType;
        $logDetails['inputRaw'] = $state->inputRaw;

        $state->logDetails = $logDetails;
    }

    protected function requestVerifyReceipt(PurchaseRequest $purchaseRequest, array $payload): array
    {
        $client = XF::app()->http()->client();
        $resp = $client->post($this->getApiEndpoint() . '/verifyReceipt', [
            'json' => [
                'receipt-data' => $payload['transactionReceipt'],
                'password' => $purchaseRequest->PaymentProfile->options['app_shared_pass'],
                'exclude-old-transactions' => true,
            ]
        ]);

        $respJson = \GuzzleHttp\json_decode($resp->getBody()->getContents(), true);
        if (isset($respJson['status']) && $respJson['status'] === 21007) {
            // @see https://developer.apple.com/documentation/appstorereceipts/verifyreceipt
            $this->forceSandbox = true;

            return $this->requestVerifyReceipt($purchaseRequest, $payload);
        }

        return $respJson;
    }

    public function verifyIAPTransaction(PurchaseRequest $purchaseRequest, array $payload): array
    {
        $respJson = $this->requestVerifyReceipt($purchaseRequest, $payload);

        /** @var XF\Entity\PaymentProviderLog $paymentLog */
        $paymentLog = XF::em()->create('XF:PaymentProviderLog');
        $paymentLog->log_type = 'info';
        $paymentLog->log_message = '[IOS] Verify receipt response';
        $paymentLog->log_details = [
            'payload' => $payload,
            'response' => $respJson,
            '_POST' => $_POST,
            'store_product_id' => $purchaseRequest->extra_data['store_product_id'],
        ];
        $paymentLog->purchase_request_key = $purchaseRequest->request_key;
        $paymentLog->provider_id = $this->getProviderId();
        $paymentLog->save();

        if (isset($respJson['status']) && $respJson['status'] === 0) {
            $latestReceipt = $respJson['latest_receipt_info'][0];
            if ($respJson['receipt']['bundle_id'] !== $purchaseRequest->PaymentProfile->options['app_bundle_id']) {
                throw new InvalidArgumentException('App bundle ID did not match');
            }

            $expires = \ceil($latestReceipt['expires_date_ms'] / 1000) + $this->getPurchaseExpiresExtraSeconds($purchaseRequest->PaymentProfile);
            if ($expires <= time()) {
                throw new PurchaseExpiredException();
            }

            if (isset($latestReceipt['transaction_id']) && $latestReceipt['in_app_ownership_type'] === 'PURCHASED') {
                $transactionId = $latestReceipt['transaction_id'];
                $subscriberId = $latestReceipt['original_transaction_id'];

                $paymentLog->fastUpdate([
                    'transaction_id' => $transactionId,
                    'subscriber_id' => $subscriberId,
                ]);

                return [
                    'transaction_id' => $transactionId,
                    'subscriber_id' => $subscriberId,
                    'signedTransaction' => [
                        'productId' => $latestReceipt['product_id'],
                    ],
                    'store_product_id' => $purchaseRequest->extra_data['store_product_id'],
                ];
            }
        }

        throw new InvalidArgumentException('Cannot verify receipt');
    }

    protected function getPurchaseExpiresExtraSeconds(PaymentProfile $paymentProfile): int
    {
        return $paymentProfile->options['expires_extra_seconds'] ?? 120;
    }

    protected function getCertificate(string $contents): string
    {
        return <<<EOF
-----BEGIN CERTIFICATE-----
{$contents}
-----END CERTIFICATE-----
EOF;
    }
}
