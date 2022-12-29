<?php

namespace Truonglv\Api\Admin\Controller;

use Truonglv\Api\App;
use XF\Mvc\ParameterBag;
use XF\ControllerPlugin\Delete;
use XF\ControllerPlugin\Toggle;
use XF\Mvc\Reply\AbstractReply;
use XF\Admin\Controller\AbstractController;

class IAPProduct extends AbstractController
{
    /**
     * @param mixed $action
     * @param ParameterBag $params
     * @return void
     */
    protected function preDispatchController($action, ParameterBag $params)
    {
        parent::preDispatchController($action, $params);

        $this->setSectionContext('tapi_iapProducts');
    }

    public function actionIndex()
    {
        $products = $this->finder($this->getEntityClassName())
            ->order('display_order')
            ->fetch();

        return $this->view(
            $this->getEntityClassName() . '\\List',
            $this->getTemplatePrefix() . '_list',
            [
                'products' => $products,
                'linkPrefix' => $this->getLinkPrefix(),
                'total' => $products->count(),
            ]
        );
    }

    public function actionAdd()
    {
        return $this->getProductForm($this->getNewProduct());
    }

    public function actionEdit(ParameterBag $params)
    {
        return $this->getProductForm($this->assertProductExists($params['product_id']));
    }

    public function actionSave(ParameterBag $params)
    {
        if ($params['product_id'] > 0) {
            $product = $this->assertProductExists($params['product_id']);
        } else {
            $product = $this->getNewProduct();
        }

        $form = $this->formAction();

        $form->basicEntitySave($product, $this->filter([
            'title' => 'str',
            'platform' => 'str',
            'store_product_id' => 'str',
            'user_upgrade_id' => 'uint',
            'active' => 'bool',
            'display_order' => 'uint',
            'payment_profile_id' => 'uint',
            'description' => 'str',
            'best_choice_offer' => 'bool',
        ]));

        $form->run();

        return $this->redirect($this->buildLink($this->getLinkPrefix()) . $this->buildLinkHash($product->product_id));
    }

    public function actionToggle()
    {
        /** @var Toggle $toggle */
        $toggle = $this->plugin('XF:Toggle');

        return $toggle->actionToggle(
            $this->getEntityClassName(),
            'active'
        );
    }

    public function actionDelete(ParameterBag $params)
    {
        $product = $this->assertProductExists($params['product_id']);

        /** @var Delete $delete */
        $delete = $this->plugin('XF:Delete');

        return $delete->actionDelete(
            $product,
            $this->buildLink($this->getLinkPrefix() . '/delete', $product),
            $this->buildLink($this->getLinkPrefix() . '/edit', $product),
            $this->buildLink($this->getLinkPrefix()),
            $product->title
        );
    }

    protected function getNewProduct(): \Truonglv\Api\Entity\IAPProduct
    {
        /** @var \Truonglv\Api\Entity\IAPProduct $product */
        $product = $this->em()->create($this->getEntityClassName());

        return $product;
    }

    /**
     * @param mixed $id
     * @return \Truonglv\Api\Entity\IAPProduct
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertProductExists($id): \Truonglv\Api\Entity\IAPProduct
    {
        /** @var \Truonglv\Api\Entity\IAPProduct $product */
        $product = $this->assertRecordExists($this->getEntityClassName(), $id);

        return $product;
    }

    protected function getProductForm(\Truonglv\Api\Entity\IAPProduct $product): AbstractReply
    {
        $userUpgrades = $this->finder('XF:UserUpgrade')->fetch();
        $paymentProfiles = $this->finder('XF:PaymentProfile')
            ->where('provider_id', [App::PAYMENT_PROVIDER_ANDROID, App::PAYMENT_PROVIDER_IOS])
            ->fetch();

        return $this->view(
            $this->getEntityClassName() . '\\Form',
            $this->getTemplatePrefix() . '_edit',
            [
                'product' => $product,
                'linkPrefix' => $this->getLinkPrefix(),
                'userUpgrades' => $userUpgrades,
                'paymentProfiles' => $paymentProfiles,
            ]
        );
    }

    protected function getTemplatePrefix(): string
    {
        return 'tapi_iap_product';
    }

    protected function getEntityClassName(): string
    {
        return 'Truonglv\Api:IAPProduct';
    }

    protected function getLinkPrefix(): string
    {
        return 'tapi-iap-products';
    }
}
