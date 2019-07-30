<?php

namespace Truonglv\Api\Admin\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\Entity\Finder;
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

    protected function doPrepareFinderForList(Finder $finder)
    {
        $finder->with('User');
        $finder->order('subscribed_date', 'desc');
    }

    public function getEntityHint($entity)
    {
        return $entity->get('device_token');
    }

    public function getEntityExplain($entity)
    {
        $language = $this->app()->language();

        return sprintf(
            '%s (%s) - %s',
            $entity->get('provider'),
            $entity->get('provider_key'),
            $language->dateTime($entity->subscribed_date)
        );
    }

    /**
     * @return string
     */
    protected function getShortName()
    {
        return 'Truonglv\Api:Subscription';
    }

    /**
     * @return string
     */
    protected function getPrefixForClasses()
    {
        return 'Truonglv\Api:Subscription';
    }

    /**
     * @return string
     */
    protected function getPrefixForPhrases()
    {
        return 'tapi_subscription';
    }

    /**
     * @return string
     */
    protected function getPrefixForTemplates()
    {
        return 'tapi';
    }

    /**
     * @return string
     */
    protected function getRoutePrefix()
    {
        return 'tapi-subscriptions';
    }

    protected function supportsAdding()
    {
        return false;
    }

    protected function supportsEditing()
    {
        return false;
    }

    protected function supportsViewing()
    {
        return true;
    }
}
