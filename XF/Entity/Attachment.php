<?php

namespace Truonglv\Api\XF\Entity;

use Truonglv\Api\App;

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
        if (!in_array($this->content_type, ['post'], true)) {
            return false;
        }

        $token = $this->app()->request()->filter('tapi_token', 'str');
        $apiKey = $this->app()->request()->getApiKey();

        list($timestamp, $token) = explode('.', $token, 2);
        if (empty($timestamp) || empty($token)) {
            return false;
        }

        $expiresAt = $timestamp + App::$attachmentTokenExpires * 60;
        if ($expiresAt <= \XF::$time) {
            return false;
        }

        $expected = md5(
            intval($timestamp)
            . $apiKey
            . $this->attachment_id
            . $this->app()->config('globalSalt')
        );

        return $token === $expected;
    }
}
