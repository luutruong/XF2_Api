<?php

namespace Truonglv\Api\Payment;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use XF\Entity\PaymentProfile;
use XF\Entity\PurchaseRequest;
use XF\Mvc\Controller;
use XF\Payment\AbstractProvider;
use XF\Payment\CallbackState;
use XF\Purchasable\Purchase;

class IOS extends AbstractProvider
{
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
        $options = \array_replace([
            'app_shared_pass' => '',
            'app_bundle_id' => '',
        ], $options);
        if (\strlen($options['app_shared_pass']) === 0) {
            $errors[] = \XF::phrase('tapi_iap_ios_please_enter_valid_app_shared_pass');

            return false;
        }

        if (\strlen($options['app_bundle_id']) === 0) {
            $errors[] = \XF::phrase('tapi_iap_ios_please_enter_valid_app_bundle_id');

            return false;
        }

        return true;
    }

    public function initiatePayment(Controller $controller, PurchaseRequest $purchaseRequest, Purchase $purchase)
    {
        throw new \LogicException('Not supported');
    }

    public function processPayment(Controller $controller, PurchaseRequest $purchaseRequest, PaymentProfile $paymentProfile, Purchase $purchase)
    {
        throw new \LogicException('Not supported');
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

        if (\stripos($signedPayload, '.') === false) {
            return $state;
        }

        $parts = \explode('.', $signedPayload, 3);
        if (\count($parts) !== 3) {
            $state->logType = 'error';
            $state->logMessage = 'Invalid JWT token';

            return $state;
        }

        list($head64, $body64, ) = $parts;
        $header = \GuzzleHttp\json_decode(\base64_decode($head64, true));

        if (!isset($header->x5c) || \count($header->x5c) !== 3 || !isset($header->alg)) {
            $state->logType = 'error';
            $state->logMessage = 'Invalid JWT token';

            return $state;
        }

        $certificate = $this->getCertificate($header->x5c[0]);
        $intermediateCertificate = $this->getCertificate($header->x5c[1]);
        $rootCertificate = $this->getCertificate($header->x5c[2]);

        $appleRootCa = \file_get_contents(
            \XF::getAddOnDirectory() . '/Truonglv/Api/AppleRootCA-g3.pem'
        );
        if (\openssl_x509_verify($rootCertificate, $appleRootCa) !== 1
            || \openssl_x509_verify($intermediateCertificate, $rootCertificate) !== 1
        ) {
            $state->logType = 'error';
            $state->logMessage = 'Certificate mismatch!';

            return $state;
        }

        try {
            $certObj = \openssl_x509_read($certificate);
            $pkeyObj = \openssl_pkey_get_public($certObj);
            $pkeyArr = \openssl_pkey_get_details($pkeyObj);

            $data = \GuzzleHttp\json_decode(\base64_decode($body64));
            $transaction = JWT::decode($data->data->signedTransactionInfo, new Key($pkeyArr['key'], $header->alg));
            $signedRenewableInfo = JWT::decode($data->data->signedRenewalInfo, new Key($pkeyArr['key'], $header->alg));
        } catch (\Throwable $e) {
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

        $state->ip = $request->getIp();
        $state->_POST = $_POST;

        // setup from subscription
        if ($originalTransactionId) {
            /** @var PurchaseRequest|null $purchaseRequest */
            $purchaseRequest = \XF::em()->findOne(
                'XF:PurchaseRequest',
                ['provider_metadata' => $originalTransactionId]
            );

            if ($purchaseRequest !== null) {
                $state->purchaseRequest = $purchaseRequest; // sets requestKey too
            } else {
                $logFinder = \XF::finder('XF:PaymentProviderLog')
                    ->where('subscriber_id', $originalTransactionId)
                    ->where('provider_id', $this->providerId)
                    ->order('log_date', 'desc');

                foreach ($logFinder->fetch() AS $log) {
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
            $state->paymentResult = CallbackState::PAYMENT_REVERSED;
        }
    }

    /**
     * @return string
     */
    public function getApiEndpoint()
    {
        if ((bool) \XF::config('enableLivePayments')) {
            return 'https://buy.itunes.apple.com';
        }

        return 'https://sandbox.itunes.apple.com';
    }

    /**
     * @param CallbackState $state
     * @return void
     */
    public function prepareLogData(CallbackState $state)
    {
        $state->logDetails['signedTransaction'] = $state->signedTransaction;
        $state->logDetails['signedRenewable'] = $state->signedRenewable;
        $state->logDetails['notificationType'] = $state->notificationType;
        $state->logDetails['inputRaw'] = $state->inputRaw;
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