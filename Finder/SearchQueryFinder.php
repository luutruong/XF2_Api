<?php

namespace Truonglv\Api\Finder;

use XF\Mvc\Entity\Finder;
use Truonglv\Api\Entity\SearchQuery;
use XF\Mvc\Entity\AbstractCollection;

/**
 * @method AbstractCollection<SearchQuery> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<SearchQuery> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method SearchQuery|null fetchOne(?int $offset = null)
 * @extends Finder<SearchQuery>
 */
class SearchQueryFinder extends Finder
{
}
