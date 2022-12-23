<?php

namespace Truonglv\Api\Payment;

use LogicException;
use XF\Mvc\Controller;
use XF\Purchasable\Purchase;
use XF\Entity\PaymentProfile;
use XF\Payment\CallbackState;
use XF\Entity\PurchaseRequest;
use XF\Payment\AbstractProvider;

class Android extends AbstractProvider
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
}
