<?php

namespace Truonglv\Api\XF\Api\Controller;

use XF\Mvc\ParameterBag;

class Thread extends XFCP_Thread
{
    public function actionPostWatch(ParameterBag $params)
    {
        $thread = $this->assertViewableThread($params->thread_id);
        if (\XF::isApiCheckingPermissions() && !$thread->canWatch()) {
            return $this->noPermission();
        }

        $visitor = \XF::visitor();
        $newState = $thread->isWatched() ? 'delete' : 'watch_no_email';

        /** @var \XF\Repository\ThreadWatch $watchRepo */
        $watchRepo = $this->repository('XF:ThreadWatch');
        $watchRepo->setWatchState($thread, $visitor, $newState);

        return $this->apiSuccess([
            'is_watched' => $newState !== 'delete'
        ]);
    }
}
