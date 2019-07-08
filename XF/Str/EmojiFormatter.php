<?php

namespace Truonglv\Api\XF\Str;

class EmojiFormatter extends XFCP_EmojiFormatter
{
    /**
     * Prefer use native render emoji in app
     *
     * @var bool
     */
    private $tApiDisableFormatToImage = false;

    /**
     * @param bool $tApiDisableFormatToImage
     */
    public function setTApiDisableFormatToImage(bool $tApiDisableFormatToImage)
    {
        $this->tApiDisableFormatToImage = $tApiDisableFormatToImage;
    }

    public function formatEmojiToImage($string)
    {
        if ($this->tApiDisableFormatToImage) {
            return $string;
        }

        return parent::formatEmojiToImage($string);
    }

    public function formatShortnameToImage($string)
    {
        if ($this->tApiDisableFormatToImage) {
            return $string;
        }

        return parent::formatShortnameToImage($string);
    }
}
