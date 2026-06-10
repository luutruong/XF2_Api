<?php

namespace Truonglv\Api\Api\Controller;

use XF;
use XF\Api\Controller\AbstractController;
use XF\Entity\FeaturedContent;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XF\Service\FeaturedContent\EditorService;

class FeaturedContentController extends AbstractController
{
    public function actionGet(ParameterBag $params)
    {
        $feature = $this->assertViewableFeature((int) $params['featured_content_id']);

        return $this->apiResult([
            'feature' => $feature->toApiResult(Entity::VERBOSITY_VERBOSE),
        ]);
    }

    public function actionPost(ParameterBag $params)
    {
        $feature = $this->assertViewableFeature((int) $params['featured_content_id']);

        $content = $feature->Content;
        if ($content === null) {
            return $this->notFound();
        }

        if (XF::isApiCheckingPermissions() && !$content->canFeatureUnfeature($error)) {
            return $this->noPermission($error);
        }

        /** @var EditorService $editor */
        $editor = $this->service(EditorService::class, $feature);
        $this->applyEditorInput($editor);

        if (!$editor->validate($errors)) {
            return $this->apiError($errors, 'validation_failed');
        }

        $feature = $editor->save();

        return $this->apiResult([
            'feature' => $feature->toApiResult(Entity::VERBOSITY_VERBOSE),
        ]);
    }

    public function actionDelete(ParameterBag $params)
    {
        $feature = $this->assertViewableFeature((int) $params['featured_content_id']);

        $content = $feature->Content;
        if ($content !== null
            && XF::isApiCheckingPermissions()
            && !$content->canFeatureUnfeature($error)
        ) {
            return $this->noPermission($error);
        }

        $feature->delete();

        return $this->apiSuccess();
    }

    protected function applyEditorInput(EditorService $editor): void
    {
        $input = $this->filter([
            'title' => '?str',
            'snippet' => '?str',
            'always_visible' => '?bool',
            'auto_featured' => '?bool',
            'feature_date' => '?uint',
        ]);

        if ($input['title'] !== null) {
            $editor->setTitle($input['title']);
        }
        if ($input['snippet'] !== null) {
            $editor->setSnippet($input['snippet']);
        }
        if ($input['always_visible'] !== null) {
            $editor->setAlwaysVisible($input['always_visible']);
        }
        if ($input['auto_featured'] !== null) {
            $editor->setAutoFeatured($input['auto_featured']);
        }
        if ($input['feature_date'] !== null && $input['feature_date'] > 0) {
            $editor->setDate($input['feature_date']);
        }
    }

    protected function assertViewableFeature(int $id): FeaturedContent
    {
        /** @var FeaturedContent|null $feature */
        $feature = $this->em()->find(FeaturedContent::class, $id);
        if ($feature === null) {
            throw $this->exception($this->notFound());
        }

        $feature->setContent($feature->getContentForStyle('article'));

        if (XF::isApiCheckingPermissions() && !$feature->canView($error)) {
            throw $this->exception($this->noPermission($error));
        }

        return $feature;
    }
}
