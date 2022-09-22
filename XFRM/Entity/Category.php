<?php

namespace Truonglv\Api\XFRM\Entity;

class Category extends XFCP_Category
{
    protected function setupApiResultData(
        \XF\Api\Result\EntityResult $result,
        $verbosity = \XFRM\Entity\Category::VERBOSITY_NORMAL,
        array $options = []
    ) {
        parent::setupApiResultData($result, $verbosity, $options);

        $breadcrumbs = [];
        foreach ($this->breadcrumb_data as $breadcrumb) {
            $breadcrumbs[] = [
                'resource_category_id' => $breadcrumb['resource_category_id'],
                'title' => $breadcrumb['title'],
            ];
        }

        $result->breadcrumbs = $breadcrumbs;
    }
}
