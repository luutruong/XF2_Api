<?php

namespace Truonglv\Api\XF\BbCode\Renderer;

use Truonglv\Api\App;
use XF\Entity\Attachment;

class ApiHtml extends XFCP_ApiHtml
{
    /**
     * @param array $ast
     * @param array $options
     * @return void
     */
    protected function setupRenderOptions(array $ast, array &$options)
    {
        parent::setupRenderOptions($ast, $options);

        $options['lightbox'] = false;
        $options['stopSmilies'] = 1;
        $options['allowUnfurl'] = false;
        $options['noProxy'] = true;
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
                    'attachment' => $attachmentRef,
                    'full' => $attachmentRef->canView() && $this->isFullAttachView($option),
                    'alt' => $attachmentRef->filename,
                    'attachmentViewUrl' => $attachmentRef->canView()
                        ? App::buildAttachmentLink($attachmentRef)
                        : '',
                ];

                return $this->templater->renderTemplate('public:tapi_bb_code_tag_attach_img', $params);
            }
        }

        return parent::renderTagAttach($children, $option, $tag, $options);
    }

    /**
     * @param mixed $content
     * @param mixed $title
     * @return string
     */
    protected function getRenderedSpoiler($content, $title = null)
    {
        return $this->templater->renderTemplate('public:tapi_bb_code_tag_spoiler', [
            'content' => new \XF\PreEscaped($content),
            'title' => ($title !== null) ? new \XF\PreEscaped($title) : null
        ]);
    }

    /**
     * @param mixed $url
     * @param array $options
     * @return string
     */
    protected function prepareTextFromUrlExtended($url, array $options)
    {
        $options['shortenUrl'] = true;

        return parent::prepareTextFromUrlExtended($url, $options);
    }

    /**
     * @param mixed $text
     * @param mixed $url
     * @param array $options
     * @return string
     */
    protected function getRenderedLink($text, $url, array $options)
    {
        $visitor = \XF::visitor();
        $proxyUrl = $url;
        $linkInfo = $this->formatter->getLinkClassTarget($url);

        if ($visitor->user_id > 0 && $linkInfo['trusted'] === true) {
            $proxyUrl = App::buildLinkProxy($url);
        }

        $html = parent::getRenderedLink($text, $proxyUrl, $options);
        $html = \trim($html);

        if ($linkInfo['type'] === 'internal') {
            $app = \XF::app();
            $path = (string) parse_url($url, PHP_URL_PATH);
            $path = ltrim($path, '/');

            $request = new \XF\Http\Request(\XF::app()->inputFilterer(), [], [], [], []);
            $match = $app->router('public')->routeToController($path, $request);
            $matchController = $match->getController();

            if (\in_array($matchController, $this->getTApiSupportedControllers(), true)) {
                $params = (string) \json_encode($match->getParams());
                $html = \substr($html, 0, 3)
                    . ' data-tapi-route="' . \htmlspecialchars($matchController) . '"'
                    . ' data-tapi-route-params="' . \htmlspecialchars($params) . '" '
                    . \substr($html, 3);
            }
        }

        return $html;
    }

    /**
     * List of controllers which supported in app
     * @return string[]
     */
    protected function getTApiSupportedControllers(): array
    {
        return [
            'XF:Category',
            'XF:Forum',
            'XF:Member',
            'XF:Post',
            'XF:Thread',
        ];
    }

    /**
     * @param mixed $content
     * @param int $userId
     * @return string
     */
    protected function getRenderedUser($content, int $userId)
    {
        $rendered = parent::getRenderedUser($content, $userId);
        $params = strval(json_encode(['user_id' => $userId]));

        return \substr($rendered, 0, 3)
            . ' data-tapi-route="XF:Member" class="link--internal"'
            . ' data-tapi-route-params="' . \htmlspecialchars($params) . '" '
            . \substr($rendered, 3);
    }
}
