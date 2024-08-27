<?php

namespace Truonglv\Api\XF\Api\Controller;

use XF\Mvc\ParameterBag;
use Truonglv\Api\Api\ControllerPlugin\Reaction;

class ConversationMessageController extends XFCP_ConversationMessageController
{
    public function actionGetTApiReactions(ParameterBag $params)
    {
        $message = $this->assertViewableMessage($params->message_id);

        /** @var Reaction $reactionPlugin */
        $reactionPlugin = $this->plugin('Truonglv\Api:Api:Reaction');

        return $reactionPlugin->actionReactions('conversation_message', $message);
    }
}
