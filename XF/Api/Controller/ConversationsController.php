<?php

namespace Truonglv\Api\XF\Api\Controller;

use Truonglv\Api\Api\ControllerPlugin\ConversationPlugin;
use XF;
use function count;
use function explode;
use function implode;
use function array_map;

class ConversationsController extends XFCP_ConversationsController
{
    public function actionGet()
    {
        $response = parent::actionGet();

        $conversationPlugin = $this->plugin(ConversationPlugin::class);
        $response = $conversationPlugin->addRecipientsIntoResult($response);
        $response = $conversationPlugin->includeLastMessage($response);

        return $response;
    }

    public function actionPost()
    {
        $this->request()->set('with_last_message', 1);
        $this->request()->set('tapi_recipients', 1);

        if ($this->request()->exists('recipients')) {
            $names = $this->filter('recipients', 'str');
            $names = explode(',', $names);
            $names = array_map('trim', $names);
            $names = array_diff($names, ['']);

            $userRepo = $this->repository(XF\Repository\UserRepository::class);
            if (count($names) > 0) {
                $users = $userRepo->getUsersByNames($names, $notFound);
                if (count($notFound) > 0) {
                    return $this->apiError(XF::phrase('following_members_not_found_x', [
                        'members' => implode(', ', $notFound)
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

        $response = parent::actionPost();
        $conversationPlugin = $this->plugin(ConversationPlugin::class);
        $response = $conversationPlugin->addRecipientsIntoResult($response);
        $response = $conversationPlugin->includeLastMessage($response);

        return $response;
    }

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
            $finder->with('Master.Users|' . XF::visitor()->user_id);
            $finder->with('Master.LastMessage');
        }

        return $finder;
    }
}
