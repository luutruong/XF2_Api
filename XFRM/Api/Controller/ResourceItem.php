<?php

namespace Truonglv\Api\XFRM\Api\Controller;

use XF;
use XF\Mvc\ParameterBag;
use XFRM\Repository\ResourceWatch;

class ResourceItem extends XFCP_ResourceItem
{
    public function actionPostWatch(ParameterBag $params)
    {
        $resource = $this->assertViewableResource($params['resource_id']);
        if (XF::isApiCheckingPermissions() && !$resource->canWatch()) {
            return $this->noPermission();
        }

        /** @var ResourceWatch $watchRepo */
        $watchRepo = $this->repository('XFRM:ResourceWatch');
        $visitor = XF::visitor();
        $newState = $resource->Watch[$visitor->user_id] === null ? 'watch' : 'delete';

        $watchRepo->setWatchState($resource, $visitor, $newState);

        return $this->apiSuccess([
            'is_watched' => $newState === 'watch',
        ]);
    }
}
