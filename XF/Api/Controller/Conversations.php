<?php

namespace Truonglv\Api\XF\Api\Controller;

use XF\Repository\User;

class Conversations extends XFCP_Conversations
{
    public function actionGet()
    {
        if ($this->filter('with_last_message', 'bool') === true) {
            $this->request()->set('tapi_last_message', true);
        }

        $response = parent::actionGet();

        /** @var \Truonglv\Api\Api\ControllerPlugin\Conversation $conversationPlugin */
        $conversationPlugin = $this->plugin('Truonglv\Api:Api:Conversation');
        $conversationPlugin->addRecipientsIntoResult($response);

        return $response;
    }

    public function actionPost()
    {
        $this->request()->set('tapi_last_message', 'bool');
        if ($this->request()->exists('recipients')) {
            $names = $this->filter('recipients', 'str');
            $names = \explode(',', $names);
            $names = \array_map('trim', $names);
            $names = array_diff($names, ['']);

            /** @var User $userRepo */
            $userRepo = $this->repository('XF:User');
            if (\count($names) > 0) {
                $users = $userRepo->getUsersByNames($names, $notFound);
                if (\count($notFound) > 0) {
                    return $this->apiError(\XF::phrase('following_members_not_found_x', [
                        'members' => \implode(', ', $notFound)
                    ]), 'recipient_not_found');
                }

                $recipientIds = [];
                /** @var \XF\Entity\User $user */
                foreach ($users as $user) {
                    $recipientIds[] = $user->user_id;
                }

                $this->request()->set('recipient_ids', $recipientIds);
            }
        }

        return parent::actionPost();
    }

    /**
     * @return \XF\Finder\ConversationUser
     */
    protected function setupConversationFinder()
    {
        $filters = $this->filter([
            'started_by' => 'str',
            'received_by' => 'str',
        ]);

        if ($filters['started_by'] !== '') {
            /** @var \XF\Entity\User|null $user */
            $user = $this->em()->findOne('XF:User', [
                'username' => $filters['started_by']
            ]);

            $this->request()->set('starter_id', $user->user_id ?? PHP_INT_MAX);
        } elseif ($filters['received_by'] !== '') {
            /** @var \XF\Entity\User|null $user */
            $user = $this->em()->findOne('XF:User', [
                'username' => $filters['received_by']
            ]);

            $this->request()->set('receiver_id', $user->user_id ?? PHP_INT_MAX);
        }

        $finder = parent::setupConversationFinder();

        if ($this->request()->exists('with_last_message')) {
            $finder->with('Master.Users|' . \XF::visitor()->user_id);
            $finder->with('Master.LastMessage');
        }

        return $finder;
    }
}
