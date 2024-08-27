<?php

namespace Truonglv\Api\XF\Api\Controller;

use XF\Service\Thread\ReplierService;
use Truonglv\Api\Api\ControllerPlugin\QuotePlugin;

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
        $quotePlugin = $this->plugin(QuotePlugin::class);
        $message = $quotePlugin->prepareMessage($message, 'post');

        $replier->setMessage($message);
    }
}
