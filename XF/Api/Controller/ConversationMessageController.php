<?php

namespace Truonglv\Api\XF\Api\Controller;

use XF\Mvc\ParameterBag;
use Truonglv\Api\Api\ControllerPlugin\ReactionPlugin;

class ConversationMessageController extends XFCP_ConversationMessageController
{
    public function actionGetTApiReactions(ParameterBag $params)
    {
        $message = $this->assertViewableMessage($params->message_id);

        $reactionPlugin = $this->plugin(ReactionPlugin::class);

        return $reactionPlugin->actionReactions('conversation_message', $message);
    }
}
