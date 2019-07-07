<?php

namespace Truonglv\Api\XF\BbCode\Renderer;

use XF\Entity\Attachment;

class SimpleHtml extends XFCP_SimpleHtml
{
    public function renderTagAttach(array $children, $option, array $tag, array $options)
    {
        $id = intval($this->renderSubTreePlain($children));

        if (!empty($options['tApiRenderTagAttach']) && !empty($id)) {
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
                    'noLightbox' => true
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
}
