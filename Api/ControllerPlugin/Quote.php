<?php

namespace Truonglv\Api\Api\ControllerPlugin;

use XF\Entity\Post;
use Truonglv\Api\App;
use XF\Api\ControllerPlugin\AbstractPlugin;

class Quote extends AbstractPlugin
{
    /**
     * @param string $message
     * @param string $contentType
     * @return string
     */
    public function prepareMessage($message, $contentType)
    {
        $template = App::QUOTE_PLACEHOLDER_TEMPLATE;
        $template = \strtr($template, [
            '{content_type}' => $contentType,
            '{content_id}' => '(\d+)',
            ',' => '\,',
            '[' => '\[',
            ']' => '\]',
        ]);

        \preg_match_all('#' . $template . '#i', $message, $matches);
        if (\count($matches[0]) === 0) {
            return $message;
        }

        $posts = $this->app->findByContentType($contentType, $matches[1], 'full');
        foreach ($matches[0] as $index => $match) {
            $postId = $matches[1][$index];
            /** @var Post|null $postRef */
            $postRef = isset($posts[$postId]) ? $posts[$postId] : null;
            if ($postRef !== null && $postRef->canView()) {
                $replacement = $postRef->getQuoteWrapper(
                    $this->app->stringFormatter()->getBbCodeForQuote($postRef->message, 'post')
                );
            } else {
                $replacement = '';
            }

            $message = \str_replace($match, $replacement, $message);
        }

        return $message;
    }
}
