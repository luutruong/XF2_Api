<?php

namespace Truonglv\Api\XF\Repository;

use XF;

class Tag extends XFCP_Tag
{
    public function getTApiTrendingTags(array $contentTypes, int $dateCutOff, int $limit, int $minUses = 1): array
    {
        $db = $this->db();
        $trending = $db->fetchAll($db->limit('
            SELECT `tag_id`, COUNT(*) AS `total`
            FROM `xf_tag_content`
            WHERE `content_type` IN (' . $db->quote($contentTypes) . ') AND content_date >= ?
            GROUP BY `tag_id`
            HAVING `total` >= ?
            ORDER BY `total` DESC
        ', $limit), [
            XF::$time - $dateCutOff * 86400,
            $minUses
        ]);

        $tagIds = [];
        foreach ($trending as $item) {
            $tagIds[] = $item['tag_id'];
        }

        if (count($tagIds) === 0) {
            return [];
        }

        $tags = $this->em->findByIds('XF:Tag', $tagIds);
        $tagNames = [];

        foreach ($tagIds as $tagId) {
            /** @var \XF\Entity\Tag|null $tagRef */
            $tagRef = $tags[$tagId] ?? null;
            if ($tagRef !== null) {
                $tagNames[] = $tagRef->tag;
            }
        }

        return $tagNames;
    }
}
