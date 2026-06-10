<?php

namespace Truonglv\Api\XF\Entity;

use XF\Api\Result\EntityResult;
use XF\Entity\FeaturedContent as FeaturedContentEntity;
use XF\Mvc\Entity\Entity;

class FeaturedContent extends XFCP_FeaturedContent
{
    protected function setupApiResultData(
        EntityResult $result,
        $verbosity = FeaturedContentEntity::VERBOSITY_NORMAL,
        array $options = []
    ) {
        $result->includeColumn([
            'featured_content_id',
            'content_type',
            'content_id',
            'content_container_id',
            'content_user_id',
            'content_username',
            'content_date',
            'content_visible',
            'feature_user_id',
            'feature_date',
            'auto_featured',
            'always_visible',
        ]);

        $result->title = $this->title;
        $result->snippet = $this->snippet;
        $result->image_url = $this->getImage();
        $result->content_link = $this->getContentLink(true);

        if ($verbosity >= Entity::VERBOSITY_VERBOSE) {
            $content = $this->Content;
            $result->content = $content !== null
                ? $content->toApiResult(Entity::VERBOSITY_NORMAL)
                : null;
        }
    }
}
