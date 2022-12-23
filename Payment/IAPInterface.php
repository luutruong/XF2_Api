<?php

namespace Truonglv\Api\Payment;

use XF\Entity\PurchaseRequest;

interface  IAPInterface
{
    public function verifyIAPTransaction(PurchaseRequest $purchaseRequest, array $payload): array;
}
