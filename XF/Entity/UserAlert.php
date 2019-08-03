<?php

namespace Truonglv\Api\XF\Entity;

use Truonglv\Api\App;
use XF\Mvc\Entity\Structure;

class UserAlert extends XFCP_UserAlert
{
    protected function setupApiResultData(
        \XF\Api\Result\EntityResult $result,
        $verbosity = self::VERBOSITY_NORMAL,
        array $options = []
    ) {
        try {
            parent::setupApiResultData($result, $verbosity, $options);
        } catch (\LogicException $e) {
        }

        if (!in_array($this->content_type, App::getSupportAlertContentTypes(), true)) {
            return;
        }

        $result->includeRelation('User');

        $result->is_unviewed = $this->isUnviewed();
        $html = $this->isAlertRenderable()
            ? $this->render()
            : '';
        if (!empty($html)) {
            // remove any html without content.
            $html = preg_replace('#<([\w]+)[^>]*></\1>#si', '', $html);

            // ensure all link in html are full.
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
        }

        $result->tapi_message_html = trim($html);
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
            && in_array($this->content_type, App::getSupportAlertContentTypes(), true)
        ) {
            if ($this->app()->options()->tApi_delayPushNotifications) {
                $this->db()->insert('xf_tapi_alert_queue', [
                    'alert_id' => $this->alert_id
                ]);
            } else {
                $this->app()
                    ->jobManager()
                    ->enqueueUnique(
                        'tApi_PN_' . $this->alert_id,
                        'Truonglv\Job:PushNotification',
                        [
                            'alert_id' => $this->alert_id
                        ]
                    );
            }
        }
    }
}
