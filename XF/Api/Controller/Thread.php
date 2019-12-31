<?php

namespace Truonglv\Api\XF\Api\Controller;

use Truonglv\Api\App;
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

    /**
     * @param \XF\Entity\Thread $thread
     * @param mixed $page
     * @param mixed $perPage
     * @return array
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function getPostsInThreadPaginated(\XF\Entity\Thread $thread, $page = 1, $perPage = null)
    {
        $postId = $this->filter('post_id', 'uint');
        if ($postId > 0) {
            /** @var \XF\Entity\Post|null $post */
            $post = $this->em()->find('XF:Post', $postId);
            if ($post !== null
                && $post->thread_id === $thread->thread_id
                && $post->canView()
            ) {
                $page = floor($post->position / $this->options()->messagesPerPage) + 1;
            }
        }

        return parent::getPostsInThreadPaginated($thread, $page, $perPage);
    }

    /**
     * @param \XF\Entity\Thread $thread
     * @return \XF\Finder\Post
     */
    protected function setupPostFinder(\XF\Entity\Thread $thread)
    {
        $finder = parent::setupPostFinder($thread);

        if (App::isRequestFromApp()) {
            $finder->resetWhere();
            $finder->resetOrder();

            $finder->where('thread_id', $thread->thread_id);
            $finder->whereOr([
                ['message_state', '=', 'visible'],
                [
                    'message_state' => 'moderated',
                    'user_id' => \XF::visitor()->user_id
                ]
            ]);

            $finder->order('position', 'ASC');
        }

        return $finder;
    }
}
