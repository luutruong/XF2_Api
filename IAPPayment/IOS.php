<?php

namespace Truonglv\Api\IAPPayment;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class IOS extends AbstractProvider
{
    public function verify(array $payload): ?string
    {
        $sharedPass = $this->config['iosSharedPass'] ?? '';
        if (\strlen($sharedPass) === 0) {
            throw new \LogicException('Must be set `sharedPass`');
        }

        $client = \XF::app()->http()->client();

        try {
            $resp = $client->post($this->getVerifyUrl(), [
                'json' => [
                    'receipt-data' => $payload['transactionReceipt'],
                    'password' => $sharedPass,
                    'exclude-old-transactions' => true,
                ]
            ]);
        } catch (\Throwable $e) {
            \XF::logException($e, false);

            return null;
        }

        $respJson = \GuzzleHttp\json_decode($resp->getBody()->getContents(), true);
        $this->log('info', 'IOS verify receipt response', $respJson);

        if (isset($respJson['status']) && $respJson['status'] === 0) {
            $latestReceipt = $respJson['latest_receipt_info'][0];
            if (isset($latestReceipt['transaction_id']) && $latestReceipt['in_app_ownership_type'] === 'PURCHASED') {
                return $latestReceipt['original_transaction_id'];
            }
        }

        return null;
    }

    public function handleIPN(array $payload): bool
    {
        if (!isset($payload['signedPayload'])) {
            $this->setError('No `signedPayload`');

            return false;
        }

        $parts = \explode('.', $payload['signedPayload'], 3);
        if (\count($parts) !== 3) {
            $this->setError('Invalid JWT token');

            return false;
        }

        list($head64, $body64, ) = $parts;
        $header = \GuzzleHttp\json_decode(\base64_decode($head64, true));

        if (!isset($header->x5c) || \count($header->x5c) !== 3 || !isset($header->alg)) {
            throw new \InvalidArgumentException('Invalid header');
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
            $this->setError('Certificate mismatch!');

            return false;
        }

        try {
            $certObj = \openssl_x509_read($certificate);
            $pkeyObj = \openssl_pkey_get_public($certObj);
            $pkeyArr = \openssl_pkey_get_details($pkeyObj);

            $data = \GuzzleHttp\json_decode(\base64_decode($body64));
            $transaction = JWT::decode($data->data->signedTransactionInfo, new Key($pkeyArr['key'], $header->alg));
            // $signedRenewableInfo = JWT::decode($data->data->signedRenewalInfo, new Key($pkeyArr['key'], $header->alg));
        } catch (\Throwable $e) {
            \XF::logException($e, false);
            return false;
        }

        $notificationType = $data->notificationType;
        $originalTransactionId = $transaction->originalTransactionId;

        if ($notificationType === 'SUBSCRIBED') {
            // should upgrade user
        } elseif ($notificationType === 'EXPIRED' || 'REFUND') {
            // should cancel user upgrade.
        }

        return true;
    }


    protected function getCertificate(string $contents): string
    {
        return <<<EOF
-----BEGIN CERTIFICATE-----
{$contents}
-----END CERTIFICATE-----
EOF;
    }

    public function getProviderId(): string
    {
        return 'ios';
    }

    protected function getVerifyUrl(): string
    {
        if ($this->enableLivePayments) {
            return 'https://buy.itunes.apple.com/verifyReceipt';
        }

        return 'https://sandbox.itunes.apple.com/verifyReceipt';
    }
}
