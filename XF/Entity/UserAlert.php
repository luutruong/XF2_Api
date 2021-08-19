<?php

namespace Truonglv\Api\XF\Entity;

use Truonglv\Api\App;
use XF\Mvc\Entity\Structure;
use Truonglv\Api\Data\Reaction;

class UserAlert extends XFCP_UserAlert
{
    /**
     * @param \XF\Api\Result\EntityResult $result
     * @param int $verbosity
     * @param array $options
     * @return void
     */
    protected function setupApiResultData(
        \XF\Api\Result\EntityResult $result,
        $verbosity = self::VERBOSITY_NORMAL,
        array $options = []
    ) {
        parent::setupApiResultData($result, $verbosity, $options);

        if (!\in_array($this->content_type, App::getSupportAlertContentTypes(), true)
            || !App::isRequestFromApp()
        ) {
            return;
        }

        $result->includeRelation('User');

        $result->is_unviewed = $this->isUnviewed();
        /** @var mixed $html */
        $html = $this->isAlertRenderable()
            ? \trim($this->render())
            : '';
        if ($html !== '') {
            // remove any html without content.
            /** @var mixed $html */
            $html = \preg_replace('#<([\w]+)[^>]*></\1>#si', '', $html);

            // ensure all link in html are full.
            \preg_match_all('#<a[^>]* href=(["\'])([^"]*)\1#i', $html, $matches);
            $baseUrl = \rtrim($this->app()->options()->boardUrl, '/');
            foreach ($matches[0] as $index => $match) {
                $link = $matches[2][$index];
                if (\substr($link, 0, 1) === '/') {
                    $fullLink = $baseUrl . $link;
                    $newMatch = \str_replace($link, $fullLink, $match);
                    $html = \str_replace($match, $newMatch, $html);
                }
            }

            if ($this->action === 'reaction') {
                \preg_match(
                    '#<span class="reaction.*"[^>]*>.*<bdi>(.+)</bdi>.*</span>#si',
                    $html,
                    $reactionMatches
                );
                if (\count($reactionMatches) > 0) {
                    $html = \str_replace($reactionMatches[0], $reactionMatches[1], $html);
                }
            }
        }
        if ($this->action === 'reaction' && isset($this->extra_data['reaction_id'])) {
            /** @var Reaction $reactionData */
            $reactionData = $this->app()->data('Truonglv\Api:Reaction');
            $reactions = $reactionData->getReactions();
            if (isset($reactions[$this->extra_data['reaction_id']])) {
                $reactionRef = $reactions[$this->extra_data['reaction_id']];
                // NOTE: For other developer who want to have a small icon in app
                // just set this key to $result object. The image URL must be canonical
                $result->tapi_alert_image = $reactionRef['imageUrl'];
            }
        }

        $result->tapi_alert_html = \trim(\strval($html));
        foreach ($this->getTApiAlertData() as $key => $value) {
            $result->__set($key, $value);
        }
    }

    public function getTApiAlertData(bool $forPush = false): array
    {
        $data = [
            'alert_id' => $this->alert_id,
            'content_id' => $this->content_id,
            'content_type' => $this->content_type,
            'tapi_extra' => [],
        ];

        if ($this->content_type === 'user'
            && $this->action === 'thread_move'
            && isset($this->extra_data['link'])
        ) {
            // issue: https://nobita.me/threads/35088/
            \preg_match('#\.(\d+)(\/?)#', $this->extra_data['link'], $matches);
            if (\count($matches) > 0) {
                if (!$forPush) {
                    $data['tapi_content_type_original'] = $data['content_type'];
                    $data['tapi_content_id_original'] = $data['content_id'];
                }

                $data['content_type'] = 'thread';
                $data['content_id'] = $matches[1];
            }
        }

        return $data;
    }

    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $apiColumns = [
            'alert_id',
            'alerted_user_id',
            'user_id',
            'username',
            'content_type',
            'content_id',
            'action',
            'event_date',
            'view_date'
        ];

        foreach ($apiColumns as $column) {
            $structure->columns[$column]['api'] = true;
        }

        return $structure;
    }

    protected function _postSave()
    {
        parent::_postSave();

        if ($this->isInsert()
            && \in_array($this->content_type, App::getSupportAlertContentTypes(), true)
        ) {
            App::alertQueueRepo()->insertQueue('alert', $this->alert_id, $this->extra_data);
        }
    }
}
