<?php

namespace Truonglv\Api\XF\Api\Controller;

use XF;
use function floor;
use XF\Entity\Poll;
use XF\Mvc\ParameterBag;
use XF\Mvc\Entity\Entity;

class ThreadController extends XFCP_ThreadController
{
    public function actionPostWatch(ParameterBag $params)
    {
        $thread = $this->assertViewableThread($params->thread_id);
        if (XF::isApiCheckingPermissions() && !$thread->canWatch()) {
            return $this->noPermission();
        }

        $visitor = XF::visitor();
        $newState = $thread->isWatched() ? 'delete' : 'watch_no_email';

        $watchRepo = $this->repository(XF\Repository\ThreadWatchRepository::class);
        $watchRepo->setWatchState($thread, $visitor, $newState);

        return $this->apiSuccess([
            'is_watched' => $newState !== 'delete'
        ]);
    }

    public function actionPostPollVote(ParameterBag $params)
    {
        $this->assertRequiredApiInput('responses');

        $thread = $this->assertViewableThread($params->thread_id);
        if (XF::isApiCheckingPermissions() && !$thread->canWatch()) {
            return $this->noPermission();
        }

        /** @var Poll|null $poll */
        $poll = $thread->discussion_type === 'poll' ? $thread->Poll : null;
        if ($poll === null) {
            return $this->noPermission();
        }

        if (XF::isApiCheckingPermissions() && !$poll->canVote()) {
            return $this->noPermission();
        }

        $voteResponseIds = $this->filter('responses', 'array-uint');

        $voter = $this->service(XF\Service\Poll\VoterService::class, $poll, $voteResponseIds);
        if (!$voter->validate($errors)) {
            return $this->error($errors);
        }

        $voter->save();

        $pollNew = $this->finder(XF\Finder\PollFinder::class)->whereId($poll->poll_id)->fetchOne();

        return $this->apiSuccess([
            'poll' => $pollNew->toApiResult(Entity::VERBOSITY_VERBOSE)
        ]);
    }

    public function actionPostViewed(ParameterBag $params)
    {
        $thread = $this->assertViewableThread($params['thread_id']);

        $threadRepo = $this->getThreadRepo();
        $threadRepo->logThreadView($thread);

        return $this->apiSuccess();
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
        if ($perPage === null) {
            $perPage = $this->options()->tApi_recordsPerPage;
        }

        $postId = $this->filter('post_id', 'uint');
        $isUnread = $this->filter('is_unread', 'bool') === true;

        if ($postId > 0) {
            /** @var \XF\Entity\Post|null $post */
            $post = $this->em()->find('XF:Post', $postId);
            if ($post !== null
                && $post->thread_id === $thread->thread_id
            ) {
                if (XF::isApiCheckingPermissions() && $post->canView()) {
                    $page = floor($post->position / $perPage) + 1;
                }
            }
        } elseif ($isUnread) {
            $visitor = XF::visitor();
            $postRepo = $this->getPostRepo();

            if ($visitor->user_id > 0) {
                $firstUnreadDate = $thread->getVisitorReadDate();
                $findFirstUnread = $postRepo->findNextPostsInThread($thread, $firstUnreadDate);
                /** @var \XF\Entity\Post|null $firstUnread */
                $firstUnread = $findFirstUnread->skipIgnored()->fetchOne();
                if ($firstUnread === null) {
                    /** @var \XF\Entity\Post|null $firstUnread */
                    $firstUnread = $thread->LastPost;
                }

                if ($firstUnread !== null) {
                    $page = floor($firstUnread->position / $perPage) + 1;
                }
            } else {
                /** @var \XF\Entity\Post|null $firstUnread */
                $firstUnread = $thread->LastPost;
                if ($firstUnread !== null) {
                    $page = floor($firstUnread->position / $perPage) + 1;
                }
            }
        }

        return parent::getPostsInThreadPaginated($thread, $page, $perPage);
    }
}
