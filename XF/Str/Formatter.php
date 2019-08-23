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

    /**
     * @param bool $bool
     * @return void
     */
    public function setTApiDisableSmilieWithSpriteParams($bool)
    {
        $this->tApiDisableSmilieWithSpriteParams = $bool;
    }

    /**
     * @param mixed $text
     * @return string|string[]|null
     */
    public function replaceSmiliesHtml($text)
    {
        if ($this->tApiDisableSmilieWithSpriteParams) {
            $this->smilieTranslate = array_filter($this->smilieTranslate, function ($value) {
                preg_match('/\0(\d+)\0/', $value, $matches);
                if (count($matches) === 0) {
                    return false;
                }

                if (!isset($this->smilieReverse[$matches[1]])) {
                    return false;
                }

                $smilieRef = $this->smilieReverse[$matches[1]];

                return count($smilieRef['sprite_params']) === 0;
            });
        }

        return parent::replaceSmiliesHtml($text);
    }
}
