<?php

namespace Truonglv\Api\Admin\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\Entity\Finder;
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

    public function getEntityHint($entity)
    {
        return $entity->get('response_code');
    }

    public function getEntityExplain($entity)
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

    /**
     * @return string
     */
    protected function getShortName()
    {
        return 'Truonglv\Api:Log';
    }

    /**
     * @return string
     */
    protected function getPrefixForClasses()
    {
        return 'Truonglv\Api:Log';
    }

    /**
     * @return string
     */
    protected function getPrefixForPhrases()
    {
        return 'tapi_log';
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
        return 'tapi-logs';
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
