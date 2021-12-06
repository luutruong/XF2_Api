<?php

namespace Truonglv\Api\XF\Pub\Controller;

use Truonglv\Api\App;
use Truonglv\Api\Entity\AccessToken;
use XF\Mvc\ParameterBag;
use XF\Repository\User;

class Attachment extends XFCP_Attachment
{
    public function actionIndex(ParameterBag $params)
    {
        $tokenText = (string) $this->request()->getServer(App::HEADER_KEY_ACCESS_TOKEN);
        $visitor = \XF::visitor();
        if (strlen($tokenText) > 0 && $visitor->user_id === 0) {
            /** @var AccessToken|null $accessToken */
            $accessToken = $this->em()->find('Truonglv\Api:AccessToken', $tokenText);
            if ($accessToken !== null && !$accessToken->isExpired()) {
                /** @var User $userRepo */
                $userRepo = $this->repository('XF:User');
                $visitor = $userRepo->getVisitor($accessToken->user_id);
                if ($visitor->user_id > 0) {
                    return \XF::asVisitor($visitor, function () use ($params) {
                        return parent::actionIndex($params);
                    });
                }
            }
        }

        return parent::actionIndex($params);
    }
}
