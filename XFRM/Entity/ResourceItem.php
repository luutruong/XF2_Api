<?php

namespace Truonglv\Api\XFRM\Entity;

use XF;
use function max;

class ResourceItem extends XFCP_ResourceItem
{
    protected function setupApiResultData(
        \XF\Api\Result\EntityResult $result,
        $verbosity = \XFRM\Entity\ResourceItem::VERBOSITY_NORMAL,
        array $options = []
    ) {
        parent::setupApiResultData($result, $verbosity, $options);

        $result->can_watch = $this->canWatch();
        $result->discussion_thread_id = $this->discussion_thread_id;

        if ($verbosity > self::VERBOSITY_NORMAL) {
            $overviewFields = [];
            $visitor = XF::visitor();
            $templater = $this->app()->templater();

            $overviewFields[] = [
                'label' => XF::phrase('author'),
                'value' => $this->User !== null ? $this->User->username : $this->username,
            ];
            $overviewFields[] = [
                'label' => XF::phrase('views'),
                'value' => $templater->filter(max($this->view_count, $this->download_count), [['number', []]]),
            ];
            $overviewFields[] = [
                // @phpstan-ignore-next-line
                'label' => XF::phrase('xfrm_first_release'),
                'value' => $this->app()->language($visitor->language_id)->date($this->resource_date, 'absolute'),
            ];
            $overviewFields[] = [
                // @phpstan-ignore-next-line
                'label' => XF::phrase('xfrm_last_update'),
                'value' => $this->app()->language($visitor->language_id)->date($this->last_update, 'absolute'),
            ];

            $result->tapi_overview_fields = $overviewFields;
        }
    }
}
