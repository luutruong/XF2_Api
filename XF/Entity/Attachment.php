<?php

namespace Truonglv\Api\XF\Entity;

class Attachment extends XFCP_Attachment
{
    public function canView(&$error = null)
    {
        if ($this->tApiValidRequestToken()) {
            /** @var \XF\Repository\Attachment $attachmentRepo */
            $attachmentRepo = $this->repository('XF:Attachment');
            $handler = $attachmentRepo->getAttachmentHandler($this->content_type);
            if (!$handler) {
                return false;
            }

            $container = $handler->getContainerEntity($this->content_id);
            if (!$container) {
                return false;
            }

            return true;
        }

        return parent::canView($error);
    }

    protected function tApiValidRequestToken()
    {
        $token = $this->app()->request()->filter('tapi_token', 'str');
        $expected = md5(
            \XF::visitor()->user_id
            . $this->attachment_id
            . $this->app()->config('globalSalt')
        );

        return $token === $expected;
    }
}
