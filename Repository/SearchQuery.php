<?php

namespace Truonglv\Api\Repository;

use XF\Mvc\Entity\Repository;

class SearchQuery extends Repository
{
    public function getTrendingQueries(): array
    {
        $cutOff = \XF::$time - 30 * 86400;
        $db = $this->app()->db();

        $results = $db->fetchAll($db->limit('
            SELECT `search_query`, COUNT(*) AS `total`
            FROM `xf_tapi_search_query`
            WHERE `created_date` >= ?
            GROUP BY `search_query`
            ORDER BY `total` DESC
        ', 20), [$cutOff]);

        return array_column($results, 'search_query');
    }
}
