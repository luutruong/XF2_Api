<?php

namespace Truonglv\Api\Finder;

use Truonglv\Api\Entity\Subscription;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Subscription> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Subscription> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Subscription|null fetchOne(?int $offset = null)
 * @extends Finder<Subscription>
 */
class SubscriptionFinder extends Finder
{
}
