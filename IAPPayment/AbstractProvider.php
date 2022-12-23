<?php

namespace Truonglv\Api\IAPPayment;

use SoapMF\Entity\IAPPackage;

abstract class AbstractProvider
{
    /**
     * @var array
     */
    protected $config = [];
    /**
     * @var bool
     */
    protected $enableLivePayments = false;
    /**
     * @var string
     */
    protected $error = '';
    /**
     * @var \XF\App
     */
    protected $app;

    abstract public function verify(array $payload): array;
    abstract public function handleIPN(array $payload): bool;

    abstract public function getProviderId(): string;

    public function __construct()
    {
        $app = \XF::app();
        $this->app = $app;

        $this->setConfig((array) $app->config('tApi_iapConfig'));
    }

    public function setConfig(array $config): self
    {
        if (isset($config['enableLivePayments'])) {
            $this->setEnableLivePayments($config['enableLivePayments']);

            unset($config['enableLivePayments']);
        }

        $this->config = $config;

        return $this;
    }

    public function setEnableLivePayments(bool $flag): self
    {
        $this->enableLivePayments = $flag;

        return $this;
    }

    /**
     * @return string
     */
    public function getError(): string
    {
        return $this->error;
    }

    public function setError(string $error): self
    {
        $this->error = $error;

        return $this;
    }

    public function getPaymentProviderLogProviderId(): string
    {
        return 'tapi_iap_' . $this->getProviderId();
    }

    protected function getPackage(): ?IAPPackage
    {
//        $package = \XF::em()->find();
    }

    public function log(string $logType, string $message, array $details, array $extra = []): void
    {
        /** @var \XF\Entity\PaymentProviderLog $logger */
        $logger = \XF::em()->create('XF:PaymentProviderLog');
        $logger->provider_id = $this->getPaymentProviderLogProviderId();
        $logger->log_type = $logType;
        $logger->log_message = $message;
        $logger->log_details = $details;
        $logger->bulkSet($extra);
        $logger->save();
    }
}
