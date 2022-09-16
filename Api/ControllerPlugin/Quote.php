<?php

namespace Truonglv\Api\Api\ControllerPlugin;

use function strtr;
use LogicException;
use XF\Entity\Post;
use Truonglv\Api\App;
use function str_replace;
use XF\Mvc\Entity\Entity;
use function preg_match_all;
use XF\Entity\ConversationMessage;
use XF\Api\ControllerPlugin\AbstractPlugin;

class Quote extends AbstractPlugin
{
    public function getMessagePlaceholders(string $message, string $contentType): array
    {
        $template = App::QUOTE_PLACEHOLDER_TEMPLATE;
        $template = strtr($template, [
            '{content_type}' => $contentType,
            '{content_id}' => '(\d+)',
            ',' => '\,',
            '[' => '\[',
            ']' => '\]',
        ]);

        preg_match_all('#' . $template . '#i', $message, $matches);
        if (count($matches[0]) === 0) {
            return [];
        }

        return [
            'full' => $matches[0],
            'ids' => $matches[1],
        ];
    }

    public function prepareMessage(string $message, string $contentType): string
    {
        $matchInfo = $this->getMessagePlaceholders($message, $contentType);
        if (\count($matchInfo) === 0) {
            return $message;
        }

        $contents = $this->app->findByContentType($contentType, $matchInfo['ids'], 'full');
        foreach ($matchInfo['full'] as $index => $match) {
            $entityId = $matchInfo['ids'][$index];
            /** @var Entity|null $entityRef */
            $entityRef = isset($contents[$entityId]) ? $contents[$entityId] : null;

            $message = str_replace(
                $match,
                $entityRef === null
                    ? ''
                    : $this->getReplacement($entityRef, $contentType),
                $message
            );
        }

        return $message;
    }

    protected function getReplacement(Entity $entity, string $contentType): string
    {
        switch ($contentType) {
            case 'post':
                /** @var Post $post */
                $post = $entity;
                if ($post->canView()) {
                    return $post->getQuoteWrapper($this->app->stringFormatter()->getBbCodeForQuote($post->message, $contentType));
                }

                return '';
            case 'conversation_message':
                /** @var ConversationMessage $message */
                $message = $entity;
                if ($message->canView()) {
                    return $message->getQuoteWrapper(
                        $this->app->stringFormatter()->getBbCodeForQuote($message->message, $contentType)
                    );
                }

                return '';
        }

        throw new LogicException('Must be implemented!');
    }
}
