<?php

namespace Truonglv\Api\XF\Pub\Controller;

use XF;
use Truonglv\Api\App;
use XF\Mvc\ParameterBag;
use Truonglv\Api\Entity\AccessToken;

class AttachmentController extends XFCP_AttachmentController
{
    public function actionIndex(ParameterBag $params)
    {
        $tokenText = (string) $this->request()->getServer(App::HEADER_KEY_ACCESS_TOKEN);
        $visitor = XF::visitor();
        if (strlen($tokenText) > 0 && $visitor->user_id === 0) {
            $accessToken = $this->em()->find(AccessToken::class, $tokenText);
            if ($accessToken !== null && !$accessToken->isExpired()) {
                $userRepo = $this->repository(XF\Repository\UserRepository::class);
                $visitor = $userRepo->getVisitor($accessToken->user_id);
                if ($visitor->user_id > 0) {
                    return XF::asVisitor($visitor, function () use ($params) {
                        return parent::actionIndex($params);
                    });
                }
            }
        }

        return parent::actionIndex($params);
    }
}
