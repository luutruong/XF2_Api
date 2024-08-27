<?php

namespace Truonglv\Api\Finder;

use XF\Mvc\Entity\Finder;
use Truonglv\Api\Entity\RefreshToken;
use XF\Mvc\Entity\AbstractCollection;

/**
 * @method AbstractCollection<RefreshToken> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<RefreshToken> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method RefreshToken|null fetchOne(?int $offset = null)
 * @extends Finder<RefreshToken>
 */
class RefreshTokenFinder extends Finder
{
}
