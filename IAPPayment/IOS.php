<?php

namespace Truonglv\Api\IAPPayment;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Truonglv\Api\Entity\IAPProduct;
use XF\Entity\PaymentProviderLog;

class IOS extends AbstractProvider
{
    public function verify(array $payload): array
    {
        $sharedPass = $this->config['iosSharedPass'] ?? '';
        if (\strlen($sharedPass) === 0) {
            throw new \LogicException('Must be set `sharedPass`');
        }

        $client = \XF::app()->http()->client();
        $resp = $client->post($this->getVerifyUrl(), [
            'json' => [
                'receipt-data' => $payload['transactionReceipt'],
                'password' => $sharedPass,
                'exclude-old-transactions' => true,
            ]
        ]);

        $respJson = \GuzzleHttp\json_decode($resp->getBody()->getContents(), true);
        $this->log('info', 'IOS verify receipt response', $respJson);

        if (isset($respJson['status']) && $respJson['status'] === 0) {
            $latestReceipt = $respJson['latest_receipt_info'][0];
            if (isset($latestReceipt['transaction_id']) && $latestReceipt['in_app_ownership_type'] === 'PURCHASED') {
                return [
                    'subscriber_id' => $latestReceipt['original_transaction_id'],
                    'transaction_id' => $latestReceipt['transaction_id'],
                ];
            }
        }

        throw new \InvalidArgumentException('Cannot verify receipt');
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
            $signedRenewableInfo = JWT::decode($data->data->signedRenewalInfo, new Key($pkeyArr['key'], $header->alg));
        } catch (\Throwable $e) {
            \XF::logException($e, false);
            return false;
        }

        $notificationType = $data->notificationType;
        $originalTransactionId = $transaction->originalTransactionId;
        $transactionId = $transaction->transactionId;

        /** @var PaymentProviderLog|null $lastTransLog */
        $lastTransLog = $this->app->finder('XF:PaymentProviderLog')
            ->where('provider_id', $this->getPaymentProviderLogProviderId())
            ->where('transaction_id', $transactionId)
            ->order('log_date', 'desc')
            ->fetchOne();
        if ($lastTransLog !== null) {
            $this->setError('Transaction already processed.');

            return false;
        }

        /** @var IAPProduct|null $product */
        $product = $this->app->finder('Truonglv\Api:IAPProduct')
            ->where('platform', 'ios')
            ->where('store_product_id', $transaction->productId)
            ->fetchOne();
        if ($product === null) {
            $this->setError('Unknown in-app product record');

            return false;
        }

        if ($notificationType === 'SUBSCRIBED') {
            // should upgrade user
            $this->log('payment', 'Received auto-renew subscription', [
                'transaction' => $transaction,
                'signedRenewableInfo' => $signedRenewableInfo
            ], [
                'transaction_id' => $transactionId,
                'subscriber_id' => $originalTransactionId,
            ]);

            if ($product->UserUpgrade !== null) {
                /** @var \XF\Service\User\Upgrade $upgrade */
                $upgrade = $this->app->service('XF:User\Upgrade', $product->UserUpgrade, \XF::visitor());
                $upgrade->upgrade();
            }
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
