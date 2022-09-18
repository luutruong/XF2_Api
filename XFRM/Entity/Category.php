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
        if ($this->breadcrumb_data) {
            foreach ($this->breadcrumb_data AS $breadcrumb) {
                $breadcrumbs[] = [
                    'category_id' => $breadcrumb['category_id'],
                    'title' => $breadcrumb['title'],
                ];
            }
        }

        $result->breadcrumbs = $breadcrumbs;
    }
}
