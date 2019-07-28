<?php

namespace Truonglv\Api\XF\Entity;

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

        $result->view_url = $this->app()
            ->router('public')
            ->buildLink('canonical:posts', $this);

        App::includeMessageHtmlIfNeeded($result, $this);
    }

    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['embed_metadata']['api'] = true;

        return $structure;
    }
}
