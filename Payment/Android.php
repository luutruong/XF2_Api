<?php

namespace Truonglv\Api\Payment;

use XF;
use function strtr;
use LogicException;
use XF\Mvc\Controller;
use function preg_match;
use XF\Purchasable\Purchase;
use XF\Entity\PaymentProfile;
use XF\Payment\CallbackState;
use XF\Entity\PurchaseRequest;
use XF\Payment\AbstractProvider;

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
        // TODO: Implement prepareLogData() method.
    }

    public function verifyIAPTransaction(PurchaseRequest $purchaseRequest, array $payload): array
    {
        $client = XF::app()->http()->client();

        $verifyUrl = 'https://androidpublisher.googleapis.com/androidpublisher/v3/applications/'
            . '{packageName}/purchases/subscriptions/{subscriptionId}/tokens/{token}';
        $verifyUrl = strtr($verifyUrl, $payload);
        $resp = $client->get($verifyUrl);

        $respJson = \GuzzleHttp\json_decode($resp->getBody()->getContents());

        /** @var \XF\Entity\PaymentProviderLog $paymentLog */
        $paymentLog = XF::em()->create('XF:PaymentProviderLog');
        $paymentLog->log_type = 'info';
        $paymentLog->log_message = 'Verify receipt response';
        $paymentLog->log_details = [
            'payload' => $payload,
            'response' => $respJson,
        ];
        $paymentLog->purchase_request_key = $purchaseRequest->request_key;
        $paymentLog->provider_id = $this->getProviderId();
        $paymentLog->save();

        // https://developers.google.com/android-publisher/api-ref/rest/v3/purchases.subscriptions#SubscriptionPurchase
        if ($respJson->resource->paymentState === 1) {
            $transactionId = $respJson->resource->orderId;
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

        throw new LogicException('Cannot verify transaction');
    }
}
