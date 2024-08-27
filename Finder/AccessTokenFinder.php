<?php

namespace Truonglv\Api\Finder;

use XF\Mvc\Entity\Finder;
use Truonglv\Api\Entity\AccessToken;
use XF\Mvc\Entity\AbstractCollection;

/**
 * @method AbstractCollection<AccessToken> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<AccessToken> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method AccessToken|null fetchOne(?int $offset = null)
 * @extends Finder<AccessToken>
 */
class AccessTokenFinder extends Finder
{
}
