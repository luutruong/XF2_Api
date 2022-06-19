<?php

namespace Truonglv\Api\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $search_query_id
 * @property string $query_text
 * @property int $user_id
 * @property int $created_date
 */
class SearchQuery extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_tapi_search_query';
        $structure->primaryKey = 'search_query_id';
        $structure->shortName = 'Truonglv\Api:SearchQuery';

        $structure->columns = [
            'search_query_id' => ['type' => self::UINT, 'nullable' => true, 'autoIncrement' => true],
            'query_text' => ['type' => self::STR, 'required' => true, 'maxLength' => 255],
            'user_id' => ['type' => self::UINT, 'default' => 0],
            'created_date' => ['type' => self::UINT, 'default' => time()]
        ];

        return $structure;
    }
}
