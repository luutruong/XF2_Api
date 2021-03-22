<?php

namespace Truonglv\Api\Api\Controller;

use XF\Api\Controller\AbstractController;

class Bookmark extends AbstractController
{
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
