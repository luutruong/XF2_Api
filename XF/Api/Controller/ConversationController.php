<?php

namespace Truonglv\Api\XF\Api\Controller;

use XF;
use XF\Mvc\ParameterBag;
use XF\Mvc\Entity\Entity;
use XF\Api\Mvc\Reply\ApiResult;
use XF\Entity\ConversationUser;
use XF\Entity\ConversationMaster;
use XF\Entity\ConversationRecipient;
use XF\Repository\ConversationMessage;

class ConversationController extends XFCP_ConversationController
{
    /**
     * @var null|ConversationUser
     */
    private $tApiUserConvo = null;

    public function actionGet(ParameterBag $params)
    {
        $response = parent::actionGet($params);
        if ($response instanceof ApiResult
            && $this->tApiUserConvo instanceof ConversationUser
        ) {
            $convoRepo = $this->repository(XF\Repository\ConversationRepository::class);
            $convoRepo->markUserConversationRead($this->tApiUserConvo);
        }

        if ($response instanceof ApiResult) {
            /** @var \Truonglv\Api\Api\ControllerPlugin\Conversation $conversationPlugin */
            $conversationPlugin = $this->plugin('Truonglv\Api:Api:Conversation');
            $conversationPlugin->addRecipientsIntoResult($response);
        }

        return $response;
    }

    public function actionGetRecipients(ParameterBag $params)
    {
        $userConvo = $this->assertViewableUserConversation($params->conversation_id);
        /** @var ConversationMaster $convoMaster */
        $convoMaster = $userConvo->Master;

        $finder = $this->finder(XF\Finder\ConversationRecipientFinder::class);
        $finder->with('User', true);
        $finder->where('recipient_state', 'active');
        $finder->where('conversation_id', $convoMaster->conversation_id);
        $finder->order('User.username');

        $recipients = [];
        /** @var ConversationRecipient $recipient */
        foreach ($finder->fetch() as $recipient) {
            $recipients[$recipient->user_id] = $recipient->User;
        }

        $data = [
            'recipients' => $this->em()->getBasicCollection($recipients)->toApiResults(),
        ];

        if ($this->filter('with_conversation', 'bool') === true) {
            $data['conversation'] = $userConvo->toApiResult(Entity::VERBOSITY_VERBOSE);
        }

        return $this->apiResult($data);
    }

    public function actionPostRecipients(ParameterBag $params)
    {
        return $this->rerouteController(__CLASS__, 'post-invite', $params);
    }

    /**
     * @param \XF\Entity\ConversationMaster $conversation
     * @param mixed $page
     * @param mixed $perPage
     * @return array
     */
    protected function getMessagesInConversationPaginated(\XF\Entity\ConversationMaster $conversation, $page = 1, $perPage = null)
    {
        if ($perPage === null) {
            $perPage = $this->options()->tApi_recordsPerPage;
        }

        $unread = (bool) $this->filter('is_unread', 'bool');
        $messageId = $this->filter('message_id', 'uint');

        $userConv = $conversation->Users[XF::visitor()->user_id];
        $convMessageRepo = $this->repository(XF\Repository\ConversationMessageRepository::class);

        if ($messageId > 0) {
            /** @var \XF\Entity\ConversationMessage|null $message */
            $message = $this->em()->find('XF:ConversationMessage', $messageId);
            if ($message !== null
                && $message->conversation_id === $conversation->conversation_id
            ) {
                $messagesBefore = $convMessageRepo->findEarlierMessages($conversation, $message)->total();
                $page = floor($messagesBefore / $perPage) + 1;
            }
        } elseif ($unread) {
            /** @var \XF\Entity\ConversationMessage|null $firstUnread */
            $firstUnread = $convMessageRepo->getFirstUnreadMessageInConversation($userConv);
            if ($firstUnread === null || $firstUnread->message_id == $conversation->last_message_id) {
                $messagesBefore = $conversation->reply_count;
            } else {
                $messagesBefore = $convMessageRepo->findEarlierMessages($conversation, $firstUnread)->total();
            }

            $page = floor($messagesBefore / $perPage) + 1;
        }

        return parent::getMessagesInConversationPaginated($conversation, $page, $perPage);
    }

    /**
     * @param int $id
     * @param array|string $with
     * @return ConversationUser
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertViewableUserConversation($id, $with = 'api')
    {
        $userConvo = parent::assertViewableUserConversation($id, $with);
        $this->tApiUserConvo = $userConvo;

        return $userConvo;
    }
}
