<?php

namespace Truonglv\Api\XF\Api\Controller;

use XF\Repository\User;

class Conversations extends XFCP_Conversations
{
    public function actionPost()
    {
        if ($this->request()->exists('recipients')) {
            $names = $this->filter('recipients', 'str');
            $names = explode(',', $names);
            $names = array_map('trim', $names);

            /** @var User $userRepo */
            $userRepo = $this->repository('XF:User');
            if (count($names)> 0) {
                $users = $userRepo->getUsersByNames($names, $notFound);
                if (count($notFound) > 0) {
                    return $this->apiError(\XF::phrase('following_members_not_found_x', [
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

        return parent::actionPost();
    }

    /**
     * @return \XF\Finder\ConversationUser
     */
    protected function setupConversationFinder()
    {
        $finder = parent::setupConversationFinder();

        $finder->with('Master.Users|' . \XF::visitor()->user_id);

        return $finder;
    }
}
