<?php

namespace Truonglv\Api\XF\Str;

class Formatter extends XFCP_Formatter
{
    /**
     * In native app the smilie which rendered by image sprite could not be render in app
     *
     * @var bool
     */
    private $tApiDisableSmilieWithSpriteParams = false;

    public function setTApiDisableSmilieWithSpriteParams($bool)
    {
        $this->tApiDisableSmilieWithSpriteParams = $bool;
    }

    public function replaceSmiliesHtml($text)
    {
        if ($this->tApiDisableSmilieWithSpriteParams) {
            $this->smilieTranslate = array_filter($this->smilieTranslate, function ($value) {
                preg_match('/\0(\d+)\0/', $value, $matches);
                if (empty($matches)) {
                    return false;
                }

                if (!isset($this->smilieCache[$matches[1]])) {
                    return false;
                }

                $smilieRef = $this->smilieCache[$matches[1]];

                return empty($smilieRef['sprite_params']);
            });
        }

        return parent::replaceSmiliesHtml($text);
    }
}
