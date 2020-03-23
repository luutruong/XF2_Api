<?php

namespace Truonglv\Api\XF\Api\Controller;

use XF\Mvc\Entity\Entity;

class Users extends XFCP_Users
{
    public function actionGetFindNames()
    {
        $this->assertRequiredApiInput('names');

        $names = $this->filter('names', 'str');
        $names = \preg_split('/,/', $names, -1, PREG_SPLIT_NO_EMPTY);

        $names = \array_map('trim', $names);
        /** @var \XF\Finder\User $userFinder */
        $userFinder = $this->finder('XF:User');
        $users = $userFinder->where('username', $names)->with('api')->fetch();

        return $this->apiResult([
            'users' => $users->toApiResults(Entity::VERBOSITY_VERBOSE)
        ]);
    }
}
