<?php

namespace Truonglv\Api\XF\Api\Controller;

use Truonglv\Api\App;
use XF\Mvc\ParameterBag;

class ConversationMessages extends XFCP_ConversationMessages
{
    public function actionPost(ParameterBag $params)
    {
        $this->request()->set(App::PARAM_KEY_INCLUDE_MESSAGE_HTML, 1);

        return parent::actionPost($params);
    }

    /**
     * @param \XF\Entity\ConversationMaster $conversation
     * @return \XF\Service\Conversation\Replier
     */
    protected function setupConversationReply(\XF\Entity\ConversationMaster $conversation)
    {
        $replier = parent::setupConversationReply($conversation);

        if (App::isRequestFromApp()) {
            $quoteMessageId = $this->filter('quote_message_id', 'uint');
            $defaultMessage = null;
            if ($quoteMessageId > 0) {
                /** @var \XF\Entity\ConversationMessage|null $message */
                $message = $this->em()->find('XF:ConversationMessage', $quoteMessageId, 'User');
                if ($message && $message->conversation_id == $conversation->conversation_id) {
                    $defaultMessage = $message->getQuoteWrapper(
                        $this->app->stringFormatter()->getBbCodeForQuote($message->message, 'conversation_message')
                    );
                }
            }

            $message = $this->filter('message', 'str');
            if ($defaultMessage !== null) {
                $message = $defaultMessage . "\n" . $message;
                $replier->setMessageContent($message);
            }
        }

        return $replier;
    }
}
