<?php

namespace Truonglv\Api\Repository;

use XF;
use XF\Mvc\Entity\Repository;

class SearchQuery extends Repository
{
    public function getTrendingQueries(): array
    {
        $cutOff = XF::$time - 30 * 86400;
        $db = $this->app()->db();

        $results = $db->fetchAll($db->limit('
            SELECT `query_text`, COUNT(*) AS `total`
            FROM `xf_tapi_search_query`
            WHERE `created_date` >= ?
            GROUP BY `query_text`
            ORDER BY `total` DESC
        ', 20), [$cutOff]);

        return array_column($results, 'query_text');
    }
}
