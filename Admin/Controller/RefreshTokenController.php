<?php

namespace Truonglv\Api\Admin\Controller;

use XF;
use function implode;
use XF\Mvc\ParameterBag;
use XF\Mvc\Entity\Finder;
use Truonglv\Api\Finder\RefreshTokenFinder;
use Truonglv\Api\DevHelper\Admin\Controller\Entity;

class RefreshToken extends Entity
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
        $userId = $this->filter('user_id', 'int');
        if ($userId > 0) {
            $finder->where('user_id', $userId);
        }

        $finder->with('User');
        $finder->setDefaultOrder('created_date', 'desc');
    }

    /**
     * @return bool
     */
    protected function supportsEditing(): bool
    {
        return false;
    }

    protected function supportsAdding(): bool
    {
        return false;
    }

    protected function getShortName(): string
    {
        return 'Truonglv\Api:RefreshToken';
    }

    protected function getPrefixForClasses(): string
    {
        return 'Truonglv\Api:RefreshToken';
    }

    protected function getPrefixForPhrases(): string
    {
        return 'tapi_refresh_token';
    }

    protected function getPrefixForTemplates(): string
    {
        return 'tapi';
    }

    protected function getRoutePrefix(): string
    {
        return 'tapi-refresh-tokens';
    }

    protected function getFinderClassName(): string
    {
        return RefreshTokenFinder::class;
    }
}
