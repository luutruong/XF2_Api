<?php

namespace Truonglv\Api\Finder;

use Truonglv\Api\Entity\IAPProduct;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<IAPProduct> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<IAPProduct> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method IAPProduct|null fetchOne(?int $offset = null)
 * @extends Finder<IAPProduct>
 */
class IAPProductFinder extends Finder
{
}
