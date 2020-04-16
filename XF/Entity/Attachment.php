<?php

namespace Truonglv\Api\XF\Entity;

use Truonglv\Api\App;

class Attachment extends XFCP_Attachment
{
    /**
     * @param mixed $error
     * @return bool
     */
    public function canView(&$error = null)
    {
        if ($this->tApiValidRequestToken()) {
            /** @var \XF\Repository\Attachment $attachmentRepo */
            $attachmentRepo = $this->repository('XF:Attachment');
            $handler = $attachmentRepo->getAttachmentHandler($this->content_type);
            if ($handler === null) {
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

    /**
     * @param \XF\Api\Result\EntityResult $result
     * @param int $verbosity
     * @param array $options
     * @return void
     */
    protected function setupApiResultData(
        \XF\Api\Result\EntityResult $result,
        $verbosity = \XF\Entity\Attachment::VERBOSITY_NORMAL,
        array $options = []
    ) {
        parent::setupApiResultData($result, $verbosity, $options);

        if (\in_array($this->content_type, $this->tApiGetSupportContentTypes(), true)) {
            $result->view_url = $this->app()->router('public')
                ->buildLink('canonical:attachments', $this, [
                    'tapi_token' => App::generateTokenForViewingAttachment($this)
                ]);
        }
    }

    /**
     * @return array
     */
    protected function tApiGetSupportContentTypes()
    {
        return [
            'conversation_message',
            'post'
        ];
    }

    /**
     * @return bool
     */
    protected function tApiValidRequestToken()
    {
        if (!\in_array($this->content_type, $this->tApiGetSupportContentTypes(), true)) {
            return false;
        }

        $token = $this->app()->request()->filter('tapi_token', 'str');
        if ($token === '' || \strpos($token, '.') === false) {
            return false;
        }
        $apiKey = $this->app()->request()->getServer(App::HEADER_KEY_API_KEY);

        list($timestamp, $token) = \explode('.', $token, 2);
        $timestamp = \intval($timestamp);
        if ($timestamp <= 0 || \trim($token) === '') {
            return false;
        }

        $expiresAt = $timestamp + \intval($this->app()->options()->tApi_attachmentTokenExpires) * 60;
        if ($expiresAt <= \XF::$time) {
            return false;
        }

        $expected = \md5(
            \intval($timestamp)
            . $apiKey
            . $this->attachment_id
            . $this->app()->config('globalSalt')
        );

        return $token === $expected;
    }
}
