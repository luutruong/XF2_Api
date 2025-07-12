<?php

namespace Truonglv\Api\XF\Api\Controller;

use XF;
use XF\Mvc\ParameterBag;
use XF\Mvc\Entity\Entity;
use XF\Api\Mvc\Reply\ApiResult;
use XF\Entity\ConversationUser;
use XF\Entity\ConversationMaster;
use XF\Entity\ConversationRecipient;
use Truonglv\Api\Api\ControllerPlugin\ConversationPlugin;
use XF\Repository\AttachmentRepository;

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
            $conversationPlugin = $this->plugin(ConversationPlugin::class);
            $conversationPlugin->addRecipientsIntoResult($response);
        }

        return $response;
    }

    public function actionGetMessageIds(ParameterBag $params)
    {
        $conversation = $this->assertViewableUserConversation($params['conversation_id']);

        $messages = $this->finder(XF\Finder\ConversationMessageFinder::class)
            ->where('conversation_id', $conversation->conversation_id)
            ->order('message_date', 'ASC')
            ->fetchColumns(['message_id']);
        $messageIds = array_column($messages, 'message_id');

        return $this->apiResult([
            'message_ids' => $messageIds,
            'total' => count($messageIds),
        ]);
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

    public function actionGetMessages(ParameterBag $params)
    {
        if ($this->request->exists('message_ids')) {
            $messageIds = $this->filter('message_ids', 'array-uint');
            if (\count($messageIds) === 0) {
                return $this->apiResult([
                    'messages' => [],
                    // not supported in this mode
                    'pagination' => null,
                ]);
            }

            if (\count($messageIds) > 50) {
                return $this->error(XF::phrase('tapi_message_ids_too_long'));
            }

            $conversation = $this->assertViewableUserConversation($params['conversation_id']);
            $finder = $this->setupMessageFinder($conversation->Master);
            $finder->whereIds($messageIds);
            $messages = $finder->fetch()->sortByList($messageIds);

            /** @var AttachmentRepository $attachmentRepo */
            $attachmentRepo = $this->repository(AttachmentRepository::class);
            $attachmentRepo->addAttachmentsToContent($messages, 'conversation_message');

            $messageResults = $messages->toApiResults();

            return $this->apiResult([
                'messages' => $messageResults,
                // not supported in this mode
                'pagination' => null,
            ]);
        }

        return parent::actionGetMessages($params);
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
            $message = $this->em()->find(XF\Entity\ConversationMessage::class, $messageId);
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
