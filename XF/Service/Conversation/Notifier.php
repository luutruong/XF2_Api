<?php

namespace Truonglv\Api\XF\Service\Conversation;

use Truonglv\Api\App;
use function in_array;

class Notifier extends XFCP_Notifier
{
    /**
     * @param mixed $actionType
     * @param array $notifyUsers
     * @param \XF\Entity\ConversationMessage|null $message
     * @param \XF\Entity\User|null $sender
     * @return array
     */
    protected function _sendNotifications(
        $actionType,
        array $notifyUsers,
        \XF\Entity\ConversationMessage $message = null,
        \XF\Entity\User $sender = null
    ) {
        if ($message !== null && in_array($actionType, ['create', 'reply'], true)) {
            App::alertQueueRepo()->insertQueue('conversation_message', $message->message_id, [
                'action' => $actionType,
            ]);
        }

        return parent::_sendNotifications($actionType, $notifyUsers, $message, $sender);
    }
}
