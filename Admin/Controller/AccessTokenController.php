<?php

namespace Truonglv\Api\Admin\Controller;

use XF;
use function md5;
use function time;
use function implode;
use XF\Mvc\ParameterBag;
use XF\Mvc\Entity\Finder;
use Truonglv\Api\Finder\AccessTokenFinder;
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

    public function getEntityExplain(XF\Mvc\Entity\Entity $entity): string
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

    public function getEntityHint(XF\Mvc\Entity\Entity $entity): string
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

    protected function entitySaveProcess(XF\Mvc\Entity\Entity $entity): XF\Mvc\FormAction
    {
        $form = parent::entitySaveProcess($entity);

        $input = [];
        if (!$entity->exists()) {
            $input['token'] = md5(XF\Util\Random::getRandomString(32));
        }

        $input['expire_date'] = time() + $this->filter('expires_in', 'uint') * 3600;
        $form->basicEntitySave($entity, $input);

        return $form;
    }

    protected function supportsEditing(): bool
    {
        return false;
    }

    protected function supportsAdding(): bool
    {
        return true;
    }

    protected function getShortName(): string
    {
        return 'Truonglv\Api:AccessToken';
    }

    protected function getPrefixForClasses(): string
    {
        return 'Truonglv\Api:AccessToken';
    }

    protected function getPrefixForPhrases(): string
    {
        return 'tapi_access_token';
    }

    protected function getPrefixForTemplates(): string
    {
        return 'tapi';
    }

    protected function getRoutePrefix(): string
    {
        return 'tapi-access-tokens';
    }

    protected function getFinderClassName(): string
    {
        return AccessTokenFinder::class;
    }
}
