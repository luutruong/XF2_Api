<?php

namespace Truonglv\Api\Finder;

use Truonglv\Api\Entity\SearchQuery;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<SearchQuery> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<SearchQuery> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method SearchQuery|null fetchOne(?int $offset = null)
 * @extends Finder<SearchQuery>
 */
class SearchQueryFinder extends Finder
{
}
