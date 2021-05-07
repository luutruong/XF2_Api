<?php

namespace Truonglv\Api\Api\Controller;

use XF\Mvc\Entity\Entity;
use XF\Api\Controller\AbstractController;

class Bookmark extends AbstractController
{
    public function actionGet()
    {
        $this->assertRequiredApiInput(['content_type']);
        $this->assertRegisteredUser();

        $contentType = $this->filter('content_type', 'str');

        $visitor = \XF::visitor();
        $finder = $this->finder('XF:BookmarkItem');

        $finder->where('user_id', $visitor->user_id);
        $finder->where('content_type', $contentType);
        $finder->order('bookmark_date', 'DESC');

        $page = $this->filterPage();
        $perPage = 20;

        $total = $finder->total();
        $this->assertValidApiPage($page, $perPage, $total);

        $results = $finder->limitByPage($page, $perPage)->fetchColumns('content_id');
        $contentIds = array_column($results, 'content_id');

        $contentFinder = $this->finder((string) $this->app()->getContentTypeEntity($contentType));
        $contentFinder->with('api');
        $contentFinder->whereIds($contentIds);

        $entities = $contentFinder->fetch();
        if (\XF::isApiCheckingPermissions()) {
            $entities = $entities->filterViewable();
        }

        $entities = $entities->sortByList($contentIds);

        return $this->apiResult([
            'entities' => $entities->toApiResults(Entity::VERBOSITY_VERBOSE),
            'pagination' => $this->getPaginationData($contentIds, $page, $perPage, $total),
        ]);
    }

    public function actionPost()
    {
        $this->assertRequiredApiInput(['content_type', 'content_id']);

        /** @var mixed|null $content */
        $content = $this->app()->findByContentType(
            $this->filter('content_type', 'str'),
            $this->filter('content_id', 'uint')
        );
        if ($content === null) {
            return $this->noPermission();
        }

        if ($content->isBookmarked()) {
            return $this->apiSuccess();
        }

        /** @var \XF\Service\Bookmark\Creator $creator */
        $creator = $this->service('XF:Bookmark\Creator', $content);

        $message = $this->filter('message', 'str');
        if (utf8_strlen($message) > 0) {
            $creator->setMessage($message);
        }

        $labels = $this->filter('labels', 'str');
        if (utf8_strlen($labels) > 0) {
            $creator->setLabels($labels);
        }

        if (!$creator->validate($errors)) {
            return $this->error($errors);
        }

        $creator->save();

        return $this->apiSuccess();
    }

    public function actionDelete()
    {
        $this->assertRequiredApiInput(['content_type', 'content_id']);

        /** @var mixed|null $content */
        $content = $this->app()->findByContentType(
            $this->filter('content_type', 'str'),
            $this->filter('content_id', 'uint')
        );
        if ($content === null) {
            return $this->noPermission();
        }
        if (!$content->isBookmarked()) {
            return $this->apiSuccess();
        }

        $bookmark = $content->getBookmark();
        $bookmark->delete();

        return $this->apiSuccess();
    }
}
