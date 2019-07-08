<?php

namespace Truonglv\Api\BbCode\Renderer;

use XF\Entity\Attachment;

class SimpleHtml extends \XF\BbCode\Renderer\SimpleHtml
{
    public function addTag($tag, array $config)
    {
        if (!in_array($tag, $this->getWhitelistTags(), true)) {
            unset($config['callback']);
        }

        parent::addTag($tag, $config);
    }

    public function renderTagUrl(array $children, $option, array $tag, array $options)
    {
        $options = array_replace($options, [
            'unfurl' => false,
            'allowUnfurl' => false
        ]);

        return parent::renderTagUrl($children, $option, $tag, $options);
    }

    public function renderTagAttach(array $children, $option, array $tag, array $options)
    {
        $id = intval($this->renderSubTreePlain($children));
        if ($id > 0) {
            $attachments = $options['attachments'];

            if (!empty($attachments[$id])) {
                /** @var Attachment $attachmentRef */
                $attachmentRef = $attachments[$id];
                $params = [
                    'id' => $id,
                    'attachment' => $attachmentRef,
                    'canView' => true,
                    'full' => $this->isFullAttachView($option),
                    'styleAttr' => $this->getAttachStyleAttr($option),
                    'alt' => $this->getImageAltText($option) ?: ($attachmentRef ? $attachmentRef->filename : ''),
                    'noLightbox' => true,
                    'tApiViewUrl' => $this->getAttachmentViewUrl($attachmentRef)
                ];

                $rendered = $this->templater->renderTemplate('public:bb_code_tag_attach', $params);
                $rendered = trim(strval($rendered));

                if (substr($rendered, 0, 4) === '<img') {
                    if (strpos($rendered, 'data-width') === false) {
                        $rendered = substr($rendered, 0, 4)
                            . ' data-width="' . $attachmentRef->Data->width . '"'
                            . substr($rendered, 4);
                    }
                    if (strpos($rendered, 'data-height') === false) {
                        $rendered = substr($rendered, 0, 4)
                            . ' data-height="' . $attachmentRef->Data->height . '"'
                            . substr($rendered, 4);
                    }
                }

                return $rendered;
            }
        }

        return parent::renderTagAttach($children, $option, $tag, $options);
    }

    protected function getWhitelistTags()
    {
        return [
            'attach',
            'left',
            'center',
            'right',
            'url',
            'font',
            'size',
            'img',
            'user',
            'plain',
            'code',
            'quote',
            'b',
            'u',
            'i',
            's',
            'color'
        ];
    }

    protected function getAttachmentViewUrl(Attachment $attachment)
    {
        $visitor = \XF::visitor();
        /** @var \XF\Api\App $app */
        $app = \XF::app();
        $token = null;

        if ($attachment->has_thumbnail) {
            $token = md5(
                $visitor->user_id
                . $attachment->attachment_id
                . $app->config('globalSalt')
            );
        }

        return $app->router('api')
            ->buildLink('full:attachments/data', $attachment, [
                'hash' => $attachment->temp_hash ?: null,
                'tapi_token' => $token
            ]);
    }
}
