<?php

namespace Truonglv\Api\Finder;

use XF\Mvc\Entity\Finder;
use Truonglv\Api\Entity\Subscription;
use XF\Mvc\Entity\AbstractCollection;

/**
 * @method AbstractCollection<Subscription> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Subscription> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Subscription|null fetchOne(?int $offset = null)
 * @extends Finder<Subscription>
 */
class SubscriptionFinder extends Finder
{
}
