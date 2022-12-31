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
use XF\Mvc\Controller;
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
use function openssl_pkey_get_public;
use function openssl_pkey_get_details;

class IOS extends AbstractProvider implements IAPInterface
{
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

        $expires = $transaction->expiresDate / 1000;
        if ($expires <= time()) {
            $state->logType = 'info';
            $state->logMessage = 'Transaction was expired!';

            return $state;
        }

        $state->subscriberId = $originalTransactionId;
        $state->transactionId = $transactionId;

        $state->ip = $request->getIp();
        $state->_POST = $_POST;

        // setup from subscription
        if ($originalTransactionId) {
            /** @var PurchaseRequest|null $purchaseRequest */
            $purchaseRequest = XF::em()->findOne(
                'XF:PurchaseRequest',
                ['provider_metadata' => $originalTransactionId]
            );

            if ($purchaseRequest !== null) {
                $state->purchaseRequest = $purchaseRequest; // sets requestKey too
            } else {
                $logFinder = XF::finder('XF:PaymentProviderLog')
                    ->where('subscriber_id', $originalTransactionId)
                    ->where('provider_id', $this->providerId)
                    ->order('log_date', 'desc');

                foreach ($logFinder->fetch() as $log) {
                    if ($log->purchase_request_key) {
                        $state->requestKey = $log->purchase_request_key;

                        break;
                    }
                }
            }
        }

        return $state;
    }

    /**
     * @param CallbackState $state
     * @return void
     */
    public function getPaymentResult(CallbackState $state)
    {
        if ($state->notificationType === 'SUBSCRIBED') {
            $state->paymentResult = CallbackState::PAYMENT_RECEIVED;
        } elseif ($state->notificationType === 'EXPIRED' || $state->notificationType === 'REFUND') {
            $state->logType = 'cancel';
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
        ];
        $paymentLog->purchase_request_key = $purchaseRequest->request_key;
        $paymentLog->provider_id = $this->getProviderId();
        $paymentLog->save();

        if (isset($respJson['status']) && $respJson['status'] === 0) {
            $latestReceipt = $respJson['latest_receipt_info'][0];
            if ($respJson['receipt']['bundle_id'] !== $purchaseRequest->PaymentProfile->options['app_bundle_id']) {
                throw new InvalidArgumentException('App bundle ID did not match');
            }

            $expires = $latestReceipt['expires_date_ms'] / 1000;
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
                ];
            }
        }

        throw new InvalidArgumentException('Cannot verify receipt');
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
