<?php

namespace Truonglv\Api\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property string $content_type
 * @property int $content_id
 * @property array $payload
 * @property int $queue_date
 *
 * GETTERS
 * @property Entity|null $Content
 */
class AlertQueue extends Entity
{
    /**
     * @param Entity|null $content
     * @return void
     */
    public function setContent(Entity $content = null)
    {
        $this->_getterCache['Content'] = $content;
    }

    /**
     * @return Entity|null
     */
    public function getContent()
    {
        if (\array_key_exists('Content', $this->_getterCache)) {
            return $this->_getterCache['Content'];
        }

        return null;
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_tapi_alert_queue';
        $structure->primaryKey = ['content_type', 'content_id'];
        $structure->shortName = 'Truonglv\Api:AlertQueue';

        $structure->columns = [
            'content_type' => ['type' => self::STR, 'required' => true,
                'allowedValues' => ['alert', 'conversation_message']],
            'content_id' => ['type' => self::UINT, 'required' => true],
            'payload' => ['type' => self::JSON_ARRAY, 'default' => []],
            'queue_date' => ['type' => self::UINT, 'default' => \XF::$time]
        ];

        $structure->getters = [
            'Content' => true
        ];

        return $structure;
    }
}
