<?php

namespace Truonglv\Api\XF\Entity;

class Post extends XFCP_Post
{
    protected function setupApiResultData(
        \XF\Api\Result\EntityResult $result,
        $verbosity = \XF\Entity\Post::VERBOSITY_NORMAL,
        array $options = []
    ) {
        parent::setupApiResultData($result, $verbosity, $options);

        $request = $this->app()->request();
        if ($request->exists('include_message_html')) {
            $bbCode = $this->app()->bbCode();

            $result->tapi_message_html = $bbCode->render($this->message, 'simpleHtml', 'api:html', $this);
        }
    }
}
