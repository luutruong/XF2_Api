<?php

namespace Truonglv\Api\Api\Controller;

use XF;
use XF\Api\Controller\AbstractController;
use XF\Entity\FeaturedContent;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Repository\FeaturedContentRepository;
use XF\Service\FeaturedContent\CreatorService;

use function count;
use function in_array;

class FeaturedContentsController extends AbstractController
{
    public function actionGet()
    {
        $page = $this->filterPage();
        $perPage = (int) $this->options()->tApi_recordsPerPage;

        $featureRepo = $this->getFeatureRepo();
        $finder = $featureRepo->findFeaturedContent(true);

        $contentType = $this->filter('content_type', 'str');
        if ($contentType !== '' && in_array($contentType, $featureRepo->getSupportedContentTypes(), true)) {
            $finder->where('content_type', $contentType);
        }

        $total = $finder->total();

        $features = $finder->limitByPage($page, $perPage)->fetch();
        $featureRepo->addContentToFeaturesForStyle($features, 'article');
        $features = $this->filterViewableFeatures($features);

        return $this->apiResult([
            'features' => $features->toApiResults(Entity::VERBOSITY_VERBOSE),
            'pagination' => $this->getPaginationData($features, $page, $perPage, $total),
        ]);
    }

    public function actionPost()
    {
        $this->assertRequiredApiInput(['content_type', 'content_id']);

        $contentType = $this->filter('content_type', 'str');
        $contentId = $this->filter('content_id', 'uint');

        $featureRepo = $this->getFeatureRepo();
        $handler = $featureRepo->getFeatureHandler($contentType, true);

        $content = $handler->getContent($contentId);
        if ($content === null) {
            return $this->notFound();
        }

        if (XF::isApiCheckingPermissions() && !$content->canFeatureUnfeature($error)) {
            return $this->noPermission($error);
        }

        if ($content->isFeatured()) {
            return $this->apiError(
                XF::phrase('this_item_has_already_been_featured'),
                'already_featured'
            );
        }

        /** @var CreatorService $creator */
        $creator = $this->service(CreatorService::class, $content);
        $this->applyCreatorInput($creator);

        if (!$creator->validate($errors)) {
            return $this->apiError($errors, 'validation_failed');
        }

        $feature = $creator->save();

        return $this->apiResult([
            'feature' => $feature->toApiResult(Entity::VERBOSITY_VERBOSE),
        ]);
    }

    protected function applyCreatorInput(CreatorService $creator): void
    {
        $input = $this->filter([
            'title' => 'str',
            'snippet' => 'str',
            'always_visible' => '?bool',
            'auto_featured' => '?bool',
        ]);

        $creator->setTitle($input['title']);
        $creator->setSnippet($input['snippet']);

        if ($input['always_visible'] !== null) {
            $creator->setAlwaysVisible($input['always_visible']);
        }
        if ($input['auto_featured'] !== null) {
            $creator->setAutoFeatured($input['auto_featured']);
        }
    }

    /**
     * @param AbstractCollection<FeaturedContent> $features
     * @return AbstractCollection<FeaturedContent>
     */
    protected function filterViewableFeatures(AbstractCollection $features): AbstractCollection
    {
        return $features->filter(static function (FeaturedContent $feature) {
            return $feature->canView() && !$feature->isIgnored();
        });
    }

    protected function getFeatureRepo(): FeaturedContentRepository
    {
        return $this->repository(FeaturedContentRepository::class);
    }
}
