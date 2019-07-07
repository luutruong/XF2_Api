<?php

namespace Truonglv\Api\XF\Entity;

class ConversationMessage extends XFCP_ConversationMessage
{
    public function getBbCodeRenderOptions($context, $type)
    {
        $options = parent::getBbCodeRenderOptions($context, $type);

        $options['tApiRenderTagAttach'] = $context === 'tapi:html';

        return $options;
    }

    protected function setupApiResultData(
        \XF\Api\Result\EntityResult $result,
        $verbosity = \XF\Entity\ConversationMessage::VERBOSITY_NORMAL,
        array $options = []
    ) {
        parent::setupApiResultData($result, $verbosity, $options);

        $request = $this->app()->request();
        if ($request->exists('include_message_html')) {
            $bbCode = $this->app()->bbCode();

            $result->tapi_message_html = $bbCode->render($this->message, 'simpleHtml', 'tapi:html', $this);
        }
    }
}
