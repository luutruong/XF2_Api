<?php

namespace Truonglv\Api\XF\Entity;

use Truonglv\Api\App;
use XF\Mvc\Entity\Structure;

class ConversationMessage extends XFCP_ConversationMessage
{
    protected function setupApiResultData(
        \XF\Api\Result\EntityResult $result,
        $verbosity = \XF\Entity\ConversationMessage::VERBOSITY_NORMAL,
        array $options = []
    ) {
        parent::setupApiResultData($result, $verbosity, $options);

        $stringFormatter = $this->app()->stringFormatter();
        $plainText = $stringFormatter->stripBbCode($this->message, [
            'stripQuote' => true
        ]);

        $result->tapi_message_plain_text = $plainText;
        $result->tapi_message_plain_text_preview = $stringFormatter->wholeWordTrim(
            $plainText,
            $this->app()->options()->tApi_discussionPreviewLength
        );

        App::attachReactions($result, $this);
        $result->tapi_is_visitor_message = (\XF::visitor()->user_id === $this->user_id);
    }

    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['embed_metadata']['api'] = true;

        return $structure;
    }
}
