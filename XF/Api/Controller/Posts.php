<?php

namespace Truonglv\Api\XF\Api\Controller;

use Truonglv\Api\App;
use XF\Mvc\ParameterBag;
use XF\Service\Thread\Replier;

class Posts extends XFCP_Posts
{
    public function actionPost(ParameterBag $params)
    {
        if (App::isRequestFromApp()) {
            $this->request()->set(App::PARAM_KEY_INCLUDE_MESSAGE_HTML, 1);
        }

        return parent::actionPost($params);
    }

    /**
     * @param \XF\Entity\Thread $thread
     * @return \XF\Service\Thread\Replier
     */
    protected function setupThreadReply(\XF\Entity\Thread $thread)
    {
        $replier = parent::setupThreadReply($thread);

        if (App::isRequestFromApp()) {
            $this->tApiPrepareMessageForReply($replier);
        }

        return $replier;
    }

    protected function tApiPrepareMessageForReply(Replier $replier): void
    {
        $message = $this->filter('message', 'str');
        /** @var \Truonglv\Api\Api\ControllerPlugin\Quote $quotePlugin */
        $quotePlugin = $this->plugin('Truonglv\Api:Api:Quote');
        $message = $quotePlugin->prepareMessage($message, 'post');

        $replier->setMessage($message);
    }
}
