<?php

namespace Truonglv\Api\Finder;

use XF\Mvc\Entity\Finder;
use Truonglv\Api\Entity\IAPProduct;
use XF\Mvc\Entity\AbstractCollection;

/**
 * @method AbstractCollection<IAPProduct> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<IAPProduct> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method IAPProduct|null fetchOne(?int $offset = null)
 * @extends Finder<IAPProduct>
 */
class IAPProductFinder extends Finder
{
}
