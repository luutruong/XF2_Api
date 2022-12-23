<?php

namespace Truonglv\Api\Payment;

use XF\Mvc\Controller;
use XF\Purchasable\Purchase;
use XF\Payment\CallbackState;
use XF\Entity\PurchaseRequest;
use XF\Payment\AbstractProvider;

class Android extends AbstractProvider
{
    public function getTitle()
    {
        // TODO: Implement getTitle() method.
    }

    public function initiatePayment(Controller $controller, PurchaseRequest $purchaseRequest, Purchase $purchase)
    {
        // TODO: Implement initiatePayment() method.
    }

    public function setupCallback(\XF\Http\Request $request)
    {
        // TODO: Implement setupCallback() method.
    }

    public function getPaymentResult(CallbackState $state)
    {
        // TODO: Implement getPaymentResult() method.
    }

    public function prepareLogData(CallbackState $state)
    {
        // TODO: Implement prepareLogData() method.
    }
}
