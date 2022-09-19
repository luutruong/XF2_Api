<?php

namespace Truonglv\Api\XF\Entity;

use XF;
use Truonglv\Api\App;
use XF\Mvc\Entity\Structure;

class Post extends XFCP_Post
{
    protected function setupApiResultData(
        \XF\Api\Result\EntityResult $result,
        $verbosity = \XF\Entity\Post::VERBOSITY_NORMAL,
        array $options = []
    ) {
        parent::setupApiResultData($result, $verbosity, $options);

        $visitor = XF::visitor();
        if ($visitor->user_id > 0) {
            $result->can_report = $this->canReport();
            $result->can_ignore = $this->User !== null && $visitor->canIgnoreUser($this->User);
            $result->is_ignored = $visitor->isIgnoring($this->user_id);
        } else {
            $result->can_report = false;
            $result->can_ignore = false;
            $result->is_ignored = false;
        }

        App::setupApiResultReactions($result, $this);
        $stringFormatter = $this->app()->stringFormatter();
        $plainText = $stringFormatter->stripBbCode($this->message, [
            'stripQuote' => true
        ]);

        $result->tapi_message_plain_text = $plainText;
        $result->tapi_message_plain_text_preview = $stringFormatter->wholeWordTrim(
            $plainText,
            $this->app()->options()->tApi_discussionPreviewLength
        );
    }

    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['embed_metadata']['api'] = true;

        return $structure;
    }
}
