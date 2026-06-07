<?php

namespace Truonglv\Api\Api\Controller;

use XF;
use XF\Api\Controller\AbstractController;
use XF\Finder\ThreadFinder;
use XF\Mvc\Entity\Entity;
use XF\Repository\AttachmentRepository;
use XF\Repository\NodeRepository;

class TrendingThreadController extends AbstractController
{
    public function actionGet()
    {
        $options = $this->options();

        $perPage = (int) $options->tApi_recordsPerPage;
        $windowDays = max(1, (int) $options->tApi_trendingWindowDays);
        $wReply = max(0, (int) $options->tApi_trendingWeightReply);
        $wReaction = max(0, (int) $options->tApi_trendingWeightReaction);
        $wView = max(0, (int) $options->tApi_trendingWeightView);

        $forumIds = $this->getTrendingForumIds();
        if (count($forumIds) === 0) {
            return $this->apiResult(['threads' => []]);
        }

        $cutoff = \XF::$time - $windowDays * 86400;

        /** @var ThreadFinder $finder */
        $finder = $this->finder(ThreadFinder::class);
        $finder
            ->where('discussion_state', 'visible')
            ->where('discussion_type', '<>', 'redirect')
            ->where('sticky', 0)
            ->where('node_id', $forumIds)
            ->where('post_date', '>=', $cutoff);

        $scoreExpr = $finder->expression(
            "(%s * {$wReply} + %s * {$wReaction} + %s * {$wView} / 10)",
            ['reply_count', 'first_post_reaction_score', 'view_count']
        );
        $finder->order($scoreExpr, 'DESC');
        $finder->limit($perPage);

        $finder->with(['FirstPost', 'LastPost']);
        $finder->with('api');

        $threads = $finder->fetch()->filterViewable();

        $posts = $this->em()->getEmptyCollection();
        /** @var \XF\Entity\Thread $thread */
        foreach ($threads as $thread) {
            $posts[$thread->first_post_id] = $thread->FirstPost;
            if ($thread->last_post_id > 0) {
                $posts[$thread->last_post_id] = $thread->LastPost;
            }
        }

        $attachmentRepo = $this->repository(AttachmentRepository::class);
        $attachmentRepo->addAttachmentsToContent($posts, 'post');

        return $this->apiResult([
            'threads' => $threads->toApiResults(Entity::VERBOSITY_NORMAL, [
                'tapi_first_post' => true,
                'tapi_fetch_image' => true,
                'tapi_last_post' => true,
            ]),
        ]);
    }

    protected function getTrendingForumIds(): array
    {
        $configured = $this->options()->tApi_trendingForums;
        $configured = is_array($configured) ? array_map('intval', $configured) : [];
        $configured = array_values(array_filter($configured, static fn($id) => $id > 0));

        if (count($configured) > 0) {
            return $configured;
        }

        $nodeRepo = $this->repository(NodeRepository::class);
        $nodes = $nodeRepo->getNodeList();

        $nodeIds = [];
        /** @var \XF\Entity\Node $node */
        foreach ($nodes as $node) {
            if ($node->node_type_id === 'Forum') {
                $nodeIds[] = $node->node_id;
            }
        }

        return $nodeIds;
    }
}
