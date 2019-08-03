<?php

namespace Truonglv\Api\XF\Service\Conversation;

use Truonglv\Api\Repository\AlertQueue;

class Notifier extends XFCP_Notifier
{
    protected function _sendNotifications(
        $actionType,
        array $notifyUsers,
        \XF\Entity\ConversationMessage $message = null,
        \XF\Entity\User $sender = null
    ) {
        if ($message && in_array($actionType, ['create', 'reply'], true)) {
            AlertQueue::queue('conversation_message', $message->message_id, [
                'action' => $actionType
            ]);
        }

        return parent::_sendNotifications($actionType, $notifyUsers, $message, $sender);
    }
}
