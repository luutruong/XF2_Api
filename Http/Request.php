<?php

namespace Truonglv\Api\Http;

class Request extends \XF\Http\Request
{
    public function setApiKey($key)
    {
        $this->server['HTTP_XF_API_KEY'] = $key;
    }

    public function setApiUser($id)
    {
        $this->server['HTTP_XF_API_USER'] = intval($id);
    }
}
