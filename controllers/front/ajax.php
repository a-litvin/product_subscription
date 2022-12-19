<?php

use BelVG\ProductSubscription\Entity\SubscriptionCartProduct;
use BelVG\ProductSubscription\Entity\SubscriptionPeriodicity;
use BelVG\ProductSubscription\ReadModel\SubscriptionCartProductReadModel;
use BelVG\ProductSubscription\Service\SubscriptionAvailabilityService;

class ProductSubscriptionAjaxModuleFrontController extends ModuleFrontController
{
    public function postProcess(): void
    {
        if (false === Tools::getValue('periodicity_id')
            || !Tools::getValue('product_id')
            || !Tools::getIsset('product_attribute_id')
        ) {
            return;
        }

        $periodicityId = (int) Tools::getValue('periodicity_id');
        $cartId = Tools::getIsset('cart_id') ? Tools::getValue('cart_id') : $this->context->cart->id;
        $cart = $this->context->cart ?? new Cart((int) $cartId);

        if (!Validate::isLoadedObject($cart)) {
            $cart->save();
            $this->context->cookie->id_cart = $cart->id;
        }

        $cartId = (int) $cart->id;
        $productId = (int) Tools::getValue('product_id');
        $productAttributeId = (int) Tools::getValue('product_attribute_id');

        $em = $this->context->controller->getContainer()->get('doctrine.orm.entity_manager');

        $subscriptionCartProduct = $em->getRepository(SubscriptionCartProduct::class)->findOneBy([
            'cartId' => $cartId,
            'productId' => $productId,
            'productAttributeId' => $productAttributeId,
        ]);


        if (0 === $periodicityId) {
            if (null !== $subscriptionCartProduct) {
                // Deleting subscription cart product
                $em->remove($subscriptionCartProduct);
            }
        } else {
            $periodicity = $em->getReference(SubscriptionPeriodicity::class, $periodicityId);

            if (null === $periodicity) {
                throw new PrestaShop\PrestaShop\Core\Exception\InvalidArgumentException(sprintf('The periodicty ID:%s is incorrect.', $periodicityId));
            }

            // Updating subscription cart product
            if (null !== $subscriptionCartProduct) {
                $subscriptionCartProduct->setPeriodicity($periodicity);
            } else {
                // Creating subscription cart product
                if (SubscriptionAvailabilityService::cartHasProduct($productId, $productAttributeId, $cartId)) {
                    $subscriptionCartProduct = new SubscriptionCartProduct($periodicity, $cartId, $productId, $productAttributeId);
                } else {
                    $subscriptionCartProduct = new SubscriptionCartProduct($periodicity, $cartId, $productId, $productAttributeId, false);
                }

                $em->persist($subscriptionCartProduct);
            }
        }

        $em->flush();

        $subscriptionCartProductReadModel = $this->context->controller->getContainer()->get(SubscriptionCartProductReadModel::class);
        $ids = $subscriptionCartProductReadModel->getActiveIdsByCartId($cartId);

        if (empty($ids)) {
            $subscription = false;
        } else {
            $subscription = true;
        }

        $action = Tools::getValue('action');

        switch ($action) {
            case 'product':
                $extraResponseData = [
                    'action' => 'updateProduct',
                    'property' => 'eventType',
                    'value' => 'updatedProductQuantity',
                ];
                break;
            case 'cart':
                $extraResponseData = [
                    'action' => 'updateCart',
                    'property' => 'reason',
                    'value' => '',
                ];
                break;
            default:
                $extraResponseData = [];
        }

        $this->ajaxRender(json_encode(array_merge($extraResponseData, [
            'success' => true,
            'subscription' => $subscription,
        ])));
    }
}
