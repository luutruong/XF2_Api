<?php

namespace Truonglv\Api\XF\Api\Controller;

use function is_array;
use function array_map;
use function preg_split;
use XF\Mvc\Entity\Entity;

class UsersController extends XFCP_UsersController
{
    public function actionGetFindNames()
    {
        $this->assertRequiredApiInput('names');

        $names = $this->filter('names', 'str');
        $names = preg_split('/,/', $names, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($names)) {
            return $this->apiResult([
                'users' => [],
            ]);
        }

        $names = array_map('trim', $names);
        $userFinder = $this->finder('XF:User');
        $users = $userFinder->where('username', $names)->with('api')->fetch();

        return $this->apiResult([
            'users' => $users->toApiResults(Entity::VERBOSITY_VERBOSE)
        ]);
    }
}
