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

        if ($resource->Watch[$visitor->user_id] === null) {
            $watchRepo->setWatchState($resource, $visitor, 'watch');
        }

        return $this->apiSuccess();
    }

    public function actionDeleteWatch(ParameterBag $params)
    {
        $resource = $this->assertViewableResource($params['resource_id']);
        if (XF::isApiCheckingPermissions() && !$resource->canWatch()) {
            return $this->noPermission();
        }

        /** @var ResourceWatch $watchRepo */
        $watchRepo = $this->repository('XFRM:ResourceWatch');
        $watchRepo->setWatchState($resource, XF::visitor(), 'delete');

        return $this->apiSuccess();
    }
}
