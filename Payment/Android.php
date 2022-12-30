<?php

namespace Truonglv\Api\Payment;

use XF;
use Throwable;
use function time;
use Google\Client;
use LogicException;
use XF\Mvc\Controller;
use function preg_match;
use function array_replace;
use XF\Purchasable\Purchase;
use XF\Entity\PaymentProfile;
use XF\Payment\CallbackState;
use XF\Entity\PurchaseRequest;
use XF\Payment\AbstractProvider;
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
        $state = new CallbackState();
        $state->inputRaw = $request->getInputRaw();

        return $state;
    }

    /**
     * @param CallbackState $state
     * @return void
     */
    public function getPaymentResult(CallbackState $state)
    {
        // TODO: Implement getPaymentResult() method.
    }

    /**
     * @param CallbackState $state
     * @return void
     */
    public function prepareLogData(CallbackState $state)
    {
        $state->logDetails = $_POST;
    }

    public function verifyIAPTransaction(PurchaseRequest $purchaseRequest, array $payload): array
    {
        $client = new Client();
        $paymentProfile = $purchaseRequest->PaymentProfile;
        $serviceAccount = \GuzzleHttp\json_decode($paymentProfile->options['service_account_json'], true);

        $client->setAuthConfig($serviceAccount);
        $client->addScope('https://www.googleapis.com/auth/androidpublisher');

        $service = new AndroidPublisher($client);
        $purchase = $service->purchases_subscriptions->get(
            $payload['package_name'],
            $payload['subscription_id'],
            $payload['token']
        );

        /** @var \XF\Entity\PaymentProviderLog $paymentLog */
        $paymentLog = XF::em()->create('XF:PaymentProviderLog');
        $paymentLog->log_type = 'info';
        $paymentLog->log_message = 'Verify receipt response';
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
        if ($purchase->getPaymentState() === 1) {
            $transactionId = $purchase->getOrderId();
            if (preg_match('#(.*)\.{2}(\d+)$#', $transactionId, $matches) === 1) {
                $subscriberId = $matches[1];
            } else {
                $subscriberId = $transactionId;
            }

            // acknowledged
            if ($purchase->getAcknowledgementState() === 0) {
                $ackBody = new AndroidPublisher\SubscriptionPurchasesAcknowledgeRequest();
                $ackBody->setDeveloperPayload(\GuzzleHttp\json_encode([
                    'user_id' => $purchaseRequest->user_id,
                    'request_key' => $purchaseRequest->request_key,
                ]));

                // perform acknowledge this payment
                $service->purchases_subscriptions->acknowledge(
                    $payload['package_name'],
                    $payload['subscription_id'],
                    $payload['token'],
                    $ackBody
                );
            }

            return [
                'transaction_id' => $transactionId,
                'subscriber_id' => $subscriberId,
            ];
        }

        throw new LogicException('Cannot verify transaction');
    }
}
