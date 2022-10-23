<?php

namespace Truonglv\Api\Admin\Controller;

use XF;
use function implode;
use XF\Mvc\ParameterBag;
use XF\Mvc\Entity\Finder;
use Truonglv\Api\DevHelper\Admin\Controller\Entity;

class AccessToken extends Entity
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

    /**
     * @param XF\Mvc\Entity\Entity $entity
     * @return string
     */
    public function getEntityExplain($entity)
    {
        /** @var \Truonglv\Api\Entity\AccessToken $entity */

        $explains = [];
        if ($entity->User === null) {
            $explains[] = XF::phrase('unknown_member');
        } else {
            $explains[] = $entity->User->username;
        }

        $language = XF::app()->userLanguage(XF::visitor());
        $explains[] = $language->dateTime($entity->created_date);

        return implode(', ', $explains);
    }

    /**
     * @param XF\Mvc\Entity\Entity $entity
     * @return string
     */
    public function getEntityHint($entity)
    {
        /** @var \Truonglv\Api\Entity\AccessToken $entity */

        $language = XF::app()->userLanguage(XF::visitor());

        return $language->dateTime($entity->expire_date);
    }

    protected function doPrepareFinderForList(Finder $finder): void
    {
        $finder->with('User');
        $finder->setDefaultOrder('created_date', 'desc');
    }

    /**
     * @return bool
     */
    protected function supportsEditing()
    {
        return false;
    }

    /**
     * @return bool
     */
    protected function supportsAdding()
    {
        return false;
    }

    /**
     * @return string
     */
    protected function getShortName()
    {
        return 'Truonglv\Api:AccessToken';
    }

    /**
     * @return string
     */
    protected function getPrefixForClasses()
    {
        return 'Truonglv\Api:AccessToken';
    }

    /**
     * @return string
     */
    protected function getPrefixForPhrases()
    {
        return 'tapi_access_token';
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
        return 'tapi-access-tokens';
    }
}
