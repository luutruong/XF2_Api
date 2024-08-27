<?php

namespace Truonglv\Api\Finder;

use XF\Mvc\Entity\Finder;
use Truonglv\Api\Entity\AlertQueue;
use XF\Mvc\Entity\AbstractCollection;

/**
 * @method AbstractCollection<AlertQueue> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<AlertQueue> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method AlertQueue|null fetchOne(?int $offset = null)
 * @extends Finder<AlertQueue>
 */
class AlertQueueFinder extends Finder
{
}
