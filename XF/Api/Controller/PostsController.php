<?php

namespace Truonglv\Api\XF\Api\Controller;


use XF\Service\Thread\ReplierService;

class PostsController extends XFCP_PostsController
{
    protected function setupThreadReply(\XF\Entity\Thread $thread)
    {
        $replier = parent::setupThreadReply($thread);
        $this->tApiPrepareMessageForReply($replier);

        return $replier;
    }

    protected function tApiPrepareMessageForReply(ReplierService $replier): void
    {
        $message = $this->filter('message', 'str');
        /** @var \Truonglv\Api\Api\ControllerPlugin\Quote $quotePlugin */
        $quotePlugin = $this->plugin('Truonglv\Api:Api:Quote');
        $message = $quotePlugin->prepareMessage($message, 'post');

        $replier->setMessage($message);
    }
}
