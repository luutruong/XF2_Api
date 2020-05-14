<?php

namespace Truonglv\Api\XF\Api\Controller;

use Truonglv\Api\App;
use XF\Repository\User;

class Conversations extends XFCP_Conversations
{
    public function actionGet()
    {
        if (App::isRequestFromApp()) {
            $this->app()->request()->set('tapi_last_message', true);
            $this->app()->request()->set(App::PARAM_KEY_INCLUDE_MESSAGE_HTML, true);
        }

        return parent::actionGet();
    }

    public function actionPost()
    {
        if ($this->request()->exists('recipients')
            && App::isRequestFromApp()
        ) {
            $names = $this->filter('recipients', 'str');
            $names = \explode(',', $names);
            $names = \array_map('trim', $names);

            /** @var User $userRepo */
            $userRepo = $this->repository('XF:User');
            if (\count($names)> 0) {
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
        $finder = parent::setupConversationFinder();

        if (App::isRequestFromApp()) {
            $finder->with('Master.Users|' . \XF::visitor()->user_id);
            $finder->with('Master.LastMessage');
        }

        return $finder;
    }
}
