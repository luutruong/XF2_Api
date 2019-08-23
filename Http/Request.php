<?php

namespace Truonglv\Api\Http;

class Request extends \XF\Http\Request
{
    /**
     * @param string $key
     * @return void
     */
    public function setApiKey($key)
    {
        $this->server['HTTP_XF_API_KEY'] = $key;
    }

    /**
     * @param mixed $id
     * @return void
     */
    public function setApiUser($id)
    {
        $this->server['HTTP_XF_API_USER'] = intval($id);
    }
}
