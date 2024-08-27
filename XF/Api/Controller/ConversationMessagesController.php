<?php

namespace Truonglv\Api\XF\Api\Controller;

use Truonglv\Api\Api\ControllerPlugin\QuotePlugin;

class ConversationMessagesController extends XFCP_ConversationMessagesController
{
    protected function setupConversationReply(\XF\Entity\ConversationMaster $conversation)
    {
        $replier = parent::setupConversationReply($conversation);

        $quoteMessageId = $this->filter('quote_message_id', 'uint');
        $defaultMessage = null;
        if ($quoteMessageId > 0) {
            /** @var \XF\Entity\ConversationMessage|null $message */
            $message = $this->em()->find('XF:ConversationMessage', $quoteMessageId, 'User');
            if ($message !== null && $message->conversation_id == $conversation->conversation_id) {
                $defaultMessage = $message->getQuoteWrapper(
                    $this->app->stringFormatter()->getBbCodeForQuote($message->message, 'conversation_message')
                );
            }
        }

        $message = $this->filter('message', 'str');
        $quotePlugin = $this->plugin(QuotePlugin::class);
        $message = $quotePlugin->prepareMessage($message, 'conversation_message');

        if ($defaultMessage !== null) {
            $message = $defaultMessage . "\n" . $message;
        }
        $replier->setMessageContent($message);

        return $replier;
    }
}
