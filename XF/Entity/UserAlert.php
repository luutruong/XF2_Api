<?php

namespace Truonglv\Api\XF\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class UserAlert extends XFCP_UserAlert
{
    protected function setupApiResultData(
        \XF\Api\Result\EntityResult $result,
        $verbosity = self::VERBOSITY_NORMAL,
        array $options = []
    ) {
        $result->includeRelation('User');
        if ($this->Content instanceof Entity) {
            $result->Content = $this->Content->toApiResult($verbosity, $options);
        }

        $result->is_unviewed = $this->isUnviewed();
        $html = $this->isAlertRenderable()
            ? $this->render()
            : '';
        preg_match_all('#<a[^>]* href=(["\'])([^"]*)\1#i', $html, $matches);
        $baseUrl = rtrim($this->app()->options()->boardUrl, '/');
        foreach ($matches[0] as $index => $match) {
            $link = $matches[2][$index];
            if (substr($link, 0, 1) === '/') {
                $fullLink = $baseUrl . $link;
                $newMatch = str_replace($link, $fullLink, $match);
                $html = str_replace($match, $newMatch, $html);
            }
        }

        preg_match('#<span class="reaction.*"[^>]*>.*<bdi>(.+)</bdi>.*</span>#si', $html, $reactionMatches);
        if ($reactionMatches) {
            $html = str_replace($reactionMatches[0], $reactionMatches[1], $html);
        }

        $result->tapi_message_html = $html;
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
}
