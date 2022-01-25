<?php

namespace Truonglv\Api\Repository;

use XF\Mvc\Entity\Repository;

class Token extends Repository
{
    public function deleteUserTokens(int $userId): void
    {
        $this->db()->delete('xf_tapi_access_token', 'user_id = ?', $userId);
    }
}
