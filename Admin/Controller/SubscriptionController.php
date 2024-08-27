<?php

namespace Truonglv\Api\Admin\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\Entity\Finder;
use Truonglv\Api\Finder\SubscriptionFinder;
use Truonglv\Api\DevHelper\Admin\Controller\Entity;

class Subscription extends Entity
{
    public function actionView(ParameterBag $params)
    {
        /** @var \Truonglv\Api\Entity\Subscription $subscription */
        $subscription = $this->assertRecordExists('Truonglv\Api:Subscription', $params->subscription_id);

        return $this->view(
            $this->getPrefixForClasses() . '\View',
            $this->getPrefixForTemplates() . '_subscription_view',
            ['entity' => $subscription]
        );
    }

    /**
     * @param Finder $finder
     * @return void
     */
    protected function doPrepareFinderForList(Finder $finder)
    {
        $finder->with('User');
        $finder->order('subscribed_date', 'desc');
    }

    public function getEntityHint(\XF\Mvc\Entity\Entity $entity): string
    {
        return $entity->get('device_token');
    }

    public function getEntityExplain(\XF\Mvc\Entity\Entity $entity): string
    {
        $language = $this->app()->language();

        return sprintf(
            '%s (%s) - %s',
            $entity->get('provider'),
            $entity->get('device_type'),
            $language->dateTime($entity->get('subscribed_date'))
        );
    }

    protected function getShortName(): string
    {
        return 'Truonglv\Api:Subscription';
    }

    protected function getPrefixForClasses(): string
    {
        return 'Truonglv\Api:Subscription';
    }

    protected function getPrefixForPhrases(): string
    {
        return 'tapi_subscription';
    }

    protected function getPrefixForTemplates(): string
    {
        return 'tapi';
    }

    protected function getRoutePrefix(): string
    {
        return 'tapi-subscriptions';
    }

    protected function supportsAdding(): bool
    {
        return false;
    }

    protected function supportsEditing(): bool
    {
        return false;
    }

    protected function supportsViewing(): bool
    {
        return true;
    }

    protected function getFinderClassName(): string
    {
        return SubscriptionFinder::class;
    }
}
