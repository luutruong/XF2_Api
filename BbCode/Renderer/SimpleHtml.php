<?php

namespace Truonglv\Api\BbCode\Renderer;

use XF\Http\Request;
use Truonglv\Api\App;
use XF\Entity\Attachment;
use Truonglv\Api\XF\Str\Formatter;
use Truonglv\Api\XF\Str\EmojiFormatter;

class SimpleHtml extends \XF\BbCode\Renderer\SimpleHtml
{
    /**
     * @param mixed $tag
     * @param array $config
     * @return void
     */
    public function addTag($tag, array $config)
    {
        if (!\in_array($tag, $this->getWhitelistTags(), true)) {
            unset($config['callback']);
        }

        parent::addTag($tag, $config);
    }

    /**
     * @param array $children
     * @param mixed $option
     * @param array $tag
     * @param array $options
     * @return string
     */
    public function renderTagCode(array $children, $option, array $tag, array $options)
    {
        $content = $this->renderSubTree($children, $options);
        // a bit like ltrim, but only remove blank lines, not leading tabs on the first line
        $content = \preg_replace('#^([ \t]*\r?\n)+#', '', $content);
        $content = \rtrim($content);

        return $this->wrapHtml('<pre>', $content, '</pre>');
    }

    /**
     * @param array $children
     * @param mixed $option
     * @param array $tag
     * @param array $options
     * @return string
     */
    public function renderTagUrl(array $children, $option, array $tag, array $options)
    {
        $options = \array_replace($options, [
            'unfurl' => false,
            'allowUnfurl' => false
        ]);

        return parent::renderTagUrl($children, $option, $tag, $options);
    }

    /**
     * @param array $children
     * @param mixed $option
     * @param array $tag
     * @param array $options
     * @return string
     */
    public function renderTagAttach(array $children, $option, array $tag, array $options)
    {
        $id = \intval($this->renderSubTreePlain($children));
        if ($id > 0) {
            $attachments = $options['attachments'];

            if (isset($attachments[$id])) {
                /** @var Attachment $attachmentRef */
                $attachmentRef = $attachments[$id];
                $params = [
                    'id' => $id,
                    'attachment' => $attachmentRef,
                    'full' => $this->isFullAttachView($option),
                    'alt' => ($this->getImageAltText($option) !== '') ?: $attachmentRef->filename,
                    'attachmentViewUrl' => $this->getAttachmentViewUrl($attachmentRef)
                ];

                return $this->templater->renderTemplate('public:tapi_bb_code_tag_attach_img', $params);
            }
        }

        return parent::renderTagAttach($children, $option, $tag, $options);
    }

    /**
     * @param array $children
     * @param mixed $option
     * @param array $tag
     * @param array $options
     * @return string|string[]|null
     */
    public function renderTagImage(array $children, $option, array $tag, array $options)
    {
        $options['noProxy'] = true;
        $options['lightbox'] = false;

        return parent::renderTagImage($children, $option, $tag, $options);
    }

    /**
     * @param array $children
     * @param mixed $option
     * @param array $tag
     * @param array $options
     * @return mixed|string
     */
    public function renderTagMedia(array $children, $option, array $tag, array $options)
    {
        $mediaKey = \trim($this->renderSubTreePlain($children));
        if (\preg_match('#[&?"\'<>\r\n]#', $mediaKey) === 1 || \strpos($mediaKey, '..') !== false) {
            return '';
        }

        $censored = $this->formatter->censorText($mediaKey);
        if ($censored != $mediaKey) {
            return '';
        }

        $provider = \strtolower($option);
        if ($provider === 'youtube') {
            return $this->wrapHtml(
                '',
                $this->renderEmbedVideoHtml(
                    $provider,
                    'https://youtube.com/watch?v=' . $mediaKey,
                    'https://img.youtube.com/vi/' . $mediaKey . '/hqdefault.jpg'
                ),
                ''
            );
        }

        return parent::renderTagMedia($children, $option, $tag, $options);
    }

    /**
     * @param array $children
     * @param mixed $option
     * @param array $tag
     * @param array $options
     * @return string
     */
    public function renderTagQuote(array $children, $option, array $tag, array $options)
    {
        if (!$children) {
            return '';
        }

        $this->trimChildrenList($children);

        $content = $this->renderSubTree($children, $options);
        if ($content === '') {
            return '';
        }

        $name = null;
        $attributes = [];
        $source = [];

        if ($option !== null && \strlen($option) > 0) {
            $parts = \explode(',', $option);
            $name = $this->filterString(\array_shift($parts), \array_merge($options, [
                'stopSmilies' => 1,
                'stopBreakConversion' => 1
            ]));

            foreach ($parts AS $part) {
                $attributeParts = \explode(':', $part, 2);
                if (isset($attributeParts[1])) {
                    $attrName = \trim($attributeParts[0]);
                    $attrValue = \trim($attributeParts[1]);
                    if ($attrName !== '' && $attrValue !== '') {
                        $attributes[$attrName] = $attrValue;
                    }
                }
            }

            if ($attributes) {
                $firstValue = \reset($attributes);
                $firstName = \key($attributes);
                if ($firstName != 'member')
                {
                    $source = ['type' => $firstName, 'id' => \intval($firstValue)];
                }
            }
        }

        return $this->wrapHtml(
            '<blockquote data-name="' . $name . '" data-source="' . \json_encode($source) . '">',
            $content,
            '</blockquote>'
        );
    }

    /**
     * @param string $provider
     * @param string $viewUrl
     * @param string $thumbnailUrl
     * @return string
     */
    protected function renderEmbedVideoHtml($provider, $viewUrl, $thumbnailUrl)
    {
        return \sprintf(
            '<video src="%s" data-thumbnail="%s" data-provider="%s"></video>',
            $viewUrl,
            $thumbnailUrl,
            $provider
        );
    }

    /**
     * @param mixed $text
     * @param mixed $url
     * @param array $options
     * @return string
     */
    protected function getRenderedLink($text, $url, array $options)
    {
        $html = parent::getRenderedLink($text, $url, $options);
        $linkInfo = $this->formatter->getLinkClassTarget($url);
        $html = \trim($html);

        if ($linkInfo['type'] === 'internal') {
            $app = \XF::app();
            if (\strpos($url, $app->options()->boardUrl) === 0) {
                $url = \substr($url, \strlen($app->options()->boardUrl));
            }
            $url = \ltrim($url, '/');
            $request = new Request(\XF::app()->inputFilterer(), [], [], [], []);
            $match = $app->router('public')->routeToController($url, $request);

            if ($match->getController()) {
                $params = (string) json_encode($match->getParams());
                $html = \substr($html, 0, 3)
                    . ' data-tapi-route="' . \htmlspecialchars($match->getController()) . '"'
                    . ' data-tapi-route-params="' . \htmlspecialchars($params) . '" '
                    . \substr($html, 3);
            }
        }

        return $html;
    }

    /**
     * @param mixed $string
     * @param array $options
     * @return string|string[]|null
     * @throws \Exception
     */
    public function filterString($string, array $options)
    {
        /** @var Formatter $formatter */
        $formatter = $this->formatter;
        $formatter->setTApiDisableSmilieWithSpriteParams(true);

        /** @var EmojiFormatter $emojiFormatter */
        $emojiFormatter = $formatter->getEmojiFormatter();
        $emojiFormatter->setTApiDisableFormatToImage(true);

        return parent::filterString($string, $options);
    }

    /**
     * List of all tags which support by app
     *
     * @return array
     */
    protected function getWhitelistTags()
    {
        return [
            'attach',
            'b',
            'center',
            'code',
            'color',
            'email',
            'font',
            'i',
            'icode',
            'img',
            'indent',
            'left',
            'list',
            'media',
            'plain',
            'quote',
            'right',
            's',
            'size',
            'u',
            'url',
            'user'
        ];
    }

    /**
     * @param Attachment $attachment
     * @return string
     */
    protected function getAttachmentViewUrl(Attachment $attachment)
    {
        return App::buildAttachmentLink($attachment);
    }
}
