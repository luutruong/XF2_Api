<?php

namespace Truonglv\Api\XF\Api\Controller;

use Truonglv\Api\Api\ControllerPlugin\Reaction;
use XF\Mvc\ParameterBag;

class ConversationMessage extends XFCP_ConversationMessage
{
    public function actionGetTApiReactions(ParameterBag $params)
    {
        $message = $this->assertViewableMessage($params->message_id);

        /** @var Reaction $reactionPlugin */
        $reactionPlugin = $this->plugin('Truonglv\Api:Api:Reaction');

        return $reactionPlugin->actionReactions('conversation_message', $message);
    }
}
