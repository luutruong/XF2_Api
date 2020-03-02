<?php

namespace Truonglv\Api\XF\Api\Controller;

use Truonglv\Api\App;
use XF\Mvc\ParameterBag;

class Posts extends XFCP_Posts
{
    public function actionPost(ParameterBag $params)
    {
        $this->request()->set(App::PARAM_KEY_INCLUDE_MESSAGE_HTML, 1);

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
            $quotePostId = $this->filter('quote_post_id', 'uint');
            $defaultMessage = null;
            if ($quotePostId > 0) {
                /** @var \XF\Entity\Post|null $post */
                $post = $this->em()->find('XF:Post', $quotePostId, 'User');
                if ($post && $post->thread_id == $thread->thread_id) {
                    if (\XF::isApiCheckingPermissions() && $post->canView()) {
                        $defaultMessage = $post->getQuoteWrapper(
                            $this->app->stringFormatter()->getBbCodeForQuote($post->message, 'post')
                        );
                    }
                }
            }

            $message = $this->filter('message', 'str');
            if ($defaultMessage !== null) {
                $message = $defaultMessage . "\n" . $message;
                $replier->setMessage($message);
            }
        }

        return $replier;
    }
}
