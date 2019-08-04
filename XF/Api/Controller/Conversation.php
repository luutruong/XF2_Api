<?php

namespace Truonglv\Api\XF\Api\Controller;

use XF\Mvc\ParameterBag;
use XF\Api\Mvc\Reply\ApiResult;
use XF\Entity\ConversationUser;
use XF\Entity\ConversationRecipient;

class Conversation extends XFCP_Conversation
{
    private $tApiUserConvo = null;

    public function actionGet(ParameterBag $params)
    {
        $response = parent::actionGet($params);
        if ($response instanceof ApiResult
            && $this->tApiUserConvo instanceof ConversationUser
        ) {
            /** @var \XF\Repository\Conversation $convoRepo */
            $convoRepo = $this->repository('XF:Conversation');
            $convoRepo->markUserConversationRead($this->tApiUserConvo);
        }

        return $response;
    }

    public function actionGetRecipients(ParameterBag $params)
    {
        $userConvo = $this->assertViewableUserConversation($params->conversation_id);

        $finder = $this->finder('XF:ConversationRecipient');
        $finder->with('User', true);
        $finder->where('recipient_state', 'active');
        $finder->where('conversation_id', $userConvo->Master->conversation_id);
        $finder->order('User.username');

        $recipients = [];
        /** @var ConversationRecipient $recipient */
        foreach ($finder->fetch() as $recipient) {
            $recipients[$recipient->user_id] = $recipient->User;
        }

        $data = [
            'recipients' => $this->em()->getBasicCollection($recipients)->toApiResults()
        ];

        return $this->apiResult($data);
    }

    public function actionPostRecipients(ParameterBag $params)
    {
        return $this->rerouteController(__CLASS__, 'post-invite', $params);
    }

    protected function assertViewableUserConversation($id, $with = 'api')
    {
        $userConvo = parent::assertViewableUserConversation($id, $with);
        $this->tApiUserConvo = $userConvo;

        return $userConvo;
    }
}
