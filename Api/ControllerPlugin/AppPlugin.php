<?php

namespace Truonglv\Api\Api\ControllerPlugin;

use XF\Repository\AddOnRepository;
use XF\Repository\AttachmentRepository;
use XF\Api\ControllerPlugin\AbstractPlugin;

class AppPlugin extends AbstractPlugin
{
    /**
     * @return array
     */
    public function getAppInfo(): array
    {
        /** @var ReactionPlugin $reactionData */
        $reactionData = $this->data('Truonglv\Api:Reaction');
        $reactions = $reactionData->getReactions();

        $addOnRepo = $this->repository(AddOnRepository::class);
        $addOns = $addOnRepo->getInstalledAddOnData();

        $attachmentRepo = $this->repository(AttachmentRepository::class);
        $constraints = $attachmentRepo->getDefaultAttachmentConstraints();

        $options = $this->app->options();
        $registrationSetup = $options->registrationSetup;

        $privacyPolicyUrl = $options->privacyPolicyUrl;
        $tosUrl = $options->tosUrl;

        $info = [
            'reactions' => $reactions,
            'apiVersion' => $addOns['Truonglv/Api']['version_id'],
            'allowRegistration' => (bool) $this->options()->registrationSetup['enabled'],
            'defaultReactionId' => \Truonglv\Api\Option\Reaction::DEFAULT_REACTION_ID,
            'defaultReactionText' => $reactions[\Truonglv\Api\Option\Reaction::DEFAULT_REACTION_ID]['text'],
            'quotePlaceholderTemplate' => \Truonglv\Api\App::QUOTE_PLACEHOLDER_TEMPLATE,
            'allowedAttachmentExtensions' => $constraints['extensions'],
            'registerMinimumAge' => $registrationSetup['requireDob'] > 0
                ? intval($registrationSetup['minimumAge'])
                : 0,
            'connectedAccountProviders' => [
                'apple' => $this->options()->tApi_caAppleProviderId,
            ],
            'appName' => $this->options()->tApi_appName,
            'xfrmEnabled' => \Truonglv\Api\App::canViewResources(),
        ];

        if ($privacyPolicyUrl['type'] === 'custom') {
            $info['privacyPolicyUrl'] = $privacyPolicyUrl['custom'];
        } elseif ($privacyPolicyUrl['type'] === 'default') {
            $info['privacyPolicyUrl'] = $this->app->router('public')->buildLink('canonical:help/privacy-policy');
        } else {
            $info['privacyPolicyUrl'] = '';
        }

        if ($tosUrl['type'] === 'custom') {
            $info['tosUrl'] =  $tosUrl['custom'];
        } elseif ($tosUrl['type'] === 'default') {
            $info['tosUrl'] = $this->app->router('public')->buildLink('canonical:help/terms');
        } else {
            $info['tosUrl'] = '';
        }

        return $info;
    }
}
