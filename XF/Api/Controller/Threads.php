<?php

namespace Truonglv\Api\XF\Api\Controller;

use Truonglv\Api\App;

class Threads extends XFCP_Threads
{
    /**
     * @param \XF\Entity\Forum $forum
     * @return \XF\Service\Thread\Creator
     */
    protected function setupThreadCreate(\XF\Entity\Forum $forum)
    {
        if (App::isRequestFromApp() && $this->request()->exists('tag_names')) {
            $tagNames = $this->filter('tag_names', 'str');
            $tagNames = \preg_split('/\,/', $tagNames, -1, PREG_SPLIT_NO_EMPTY);

            $this->request()->set('tags', $tagNames);
        }

        return parent::setupThreadCreate($forum);
    }
}
