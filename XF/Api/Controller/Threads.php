<?php

namespace Truonglv\Api\XF\Api\Controller;

class Threads extends XFCP_Threads
{
    /**
     * @param \XF\Entity\Forum $forum
     * @return \XF\Service\Thread\Creator
     */
    protected function setupThreadCreate(\XF\Entity\Forum $forum)
    {
        if ($this->request()->exists('tag_names')) {
            $tagNames = $this->filter('tag_names', 'str');
            $tagNames = \preg_split('/\,/', $tagNames, -1, PREG_SPLIT_NO_EMPTY);

            $this->request()->set('tags', $tagNames);
        }

        return parent::setupThreadCreate($forum);
    }

    /**
     * @param array $filters
     * @param mixed $sort
     * @return \XF\Finder\Thread
     */
    protected function setupThreadFinder(&$filters = [], &$sort = null)
    {
        if ($this->request()->exists('started_by')) {
            $starterName = $this->filter('started_by', 'str');
            if (strlen($starterName) > 0) {
                /** @var \XF\Entity\User $user */
                $user = $this->em()->findOne('XF:User', [
                    'username' => $starterName
                ]);

                $this->request()->set('starter_id', $user->user_id ?? \PHP_INT_MAX);
            }
        }

        $finder = parent::setupThreadFinder($filters, $sort);
        if ($this->filter('with_first_post', 'bool') === true) {
            $finder->with('FirstPost');
        }

        return $finder;
    }
}
