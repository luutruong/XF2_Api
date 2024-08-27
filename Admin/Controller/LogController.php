<?php

namespace Truonglv\Api\Admin\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\Entity\Finder;
use Truonglv\Api\Finder\LogFinder;
use Truonglv\Api\DevHelper\Admin\Controller\Entity;

class Log extends Entity
{
    /**
     * @param mixed $action
     * @param ParameterBag $params
     * @throws \XF\Mvc\Reply\Exception
     * @return void
     */
    protected function preDispatchController($action, ParameterBag $params)
    {
        parent::preDispatchController($action, $params);

        $this->assertAdminPermission('logs');
    }

    public function actionView(ParameterBag $params)
    {
        /** @var \Truonglv\Api\Entity\Log $log */
        $log = $this->assertRecordExists('Truonglv\Api:Log', $params->log_id);

        return $this->view(
            $this->getPrefixForClasses() . '\View',
            $this->getPrefixForTemplates() . '_log_view',
            ['log' => $log]
        );
    }

    public function getEntityHint(\XF\Mvc\Entity\Entity $entity): string
    {
        return $entity->get('response_code');
    }

    public function getEntityExplain(\XF\Mvc\Entity\Entity $entity): string
    {
        if (!$entity instanceof \Truonglv\Api\Entity\Log) {
            return '';
        }
        $language = $this->app()->language();

        return sprintf(
            '%s - %s',
            $entity->User !== null ? $entity->User->username : '',
            $language->dateTime($entity->log_date)
        );
    }

    /**
     * @param Finder $finder
     * @return void
     */
    protected function doPrepareFinderForList(Finder $finder)
    {
        $finder->with('User');
        $finder->order('log_date', 'desc');
    }

    protected function getShortName(): string
    {
        return 'Truonglv\Api:Log';
    }

    protected function getPrefixForClasses(): string
    {
        return 'Truonglv\Api:Log';
    }

    protected function getPrefixForPhrases(): string
    {
        return 'tapi_log';
    }

    protected function getPrefixForTemplates(): string
    {
        return 'tapi';
    }

    protected function getRoutePrefix(): string
    {
        return 'tapi-logs';
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
        return LogFinder::class;
    }
}
