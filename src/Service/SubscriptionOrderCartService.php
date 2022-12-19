<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Service;

use BelVG\ProductSubscription\DTO\ProductDTO;
use BelVG\ProductSubscription\Entity\Subscription;
use BelVG\ProductSubscription\Service\Cart\CartCloneService;
use BelVG\ProductSubscription\Service\Cart\CartContextService;
use Doctrine\ORM\EntityManagerInterface;
use Cart;
use Context;
use CustomerMessage;
use CustomerThread;
use Tools;
use Validate;

class SubscriptionOrderCartService
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var CartCloneService
     */
    private $cartCloneService;

    /**
     * @var CartContextService
     */
    private $cartContextService;

    /**
     * @param EntityManagerInterface $entityManager
     * @param CartCloneService $cartCloneService
     */
    public function __construct(EntityManagerInterface $entityManager, CartCloneService $cartCloneService)
    {
        $this->entityManager = $entityManager;
        $this->cartCloneService = $cartCloneService;
        $this->cartContextService = new CartContextService(Context::getContext());
    }

    /**
     * @param Subscription $subscription
     *
     * @return Cart
     */
    public function getClonedOrderCart(Subscription $subscription): Cart
    {
        $this->cartContextService->clearCookie();
        $this->cartContextService->setSubscriptionToCookie($subscription);

        $subscriptionCart = new Cart($subscription->getCartId());
        $clonedCart = $this->cartCloneService->getCartClone($subscriptionCart->id);

        $this->cartContextService->setSubscriptionCopyCartToCookie((int) $clonedCart->id);

        $products = $subscriptionCart->getProducts();

        foreach ($products as $product) {
            $productDTO = ProductDTO::createFromPrestashopProduct($product);
            $subscriptionProduct = $subscription->getSubscriptionProductByProductDTO($productDTO);

            if (null !== $subscriptionProduct && $subscriptionProduct->isSkipNextShipmentOnly()) {
                continue;
            }

            $this->cartCloneService->updateCartProducts($clonedCart, $productDTO);
        }

        $this->cartContextService->replaceCartContext($clonedCart);

        return $clonedCart;
    }

    /**
     * @param Subscription $subscription
     */
    public function updateSubscriptionAfterShipment(Subscription $subscription): void
    {
        $subscriptionCart = new Cart($subscription->getCartId());
        $products = $subscriptionCart->getProducts();

        foreach ($products as $product) {
            $productDTO = ProductDTO::createFromPrestashopProduct($product);
            $subscriptionProduct = $subscription->getSubscriptionProductByProductDTO($productDTO);

            if (null !== $subscriptionProduct && $subscriptionProduct->isSkipNextShipmentOnly()) {
                $subscriptionProduct->setScheduled();
                continue;
            }

            if (null !== $subscriptionProduct && $subscriptionProduct->isNextShipmentOnly()) {
                $subscriptionCart->deleteProduct($productDTO->productId, $productDTO->productAttributeId);
                $this->entityManager->remove($subscriptionProduct);
            }
        }

        if (!$subscriptionCart->hasProducts()) {
            $subscription->remove();
        } else {
            $subscription->setNextDelivery(
                (new \DateTimeImmutable('today'))
                    ->modify(sprintf('+ %d days', $subscription->getPeriodicity()->getInterval()))
            );
        }

        $this->entityManager->flush();
    }

    /**
     * @param Subscription $subscription
     */
    public function removeSubscription(Subscription $subscription): void
    {
        $subscription->remove();
        $this->entityManager->flush();
    }

    /**
     * @param Subscription $subscription
     * @param int $orderId
     * @param Context $context
     */
    public function addMessageToOrder(Subscription $subscription, int $orderId, Context $context): void
    {
        $message = $subscription->getCustomerMessage();

        if (null === $message || empty($message)) {
            return;
        }

        $customer_thread = new CustomerThread();
        $customer_thread->id_contact = 0;
        $customer_thread->id_customer = (int) $context->cart->id_customer;
        $customer_thread->id_shop = (int) $context->shop->id;
        $customer_thread->id_order = $orderId;
        $customer_thread->id_lang = (int) $context->language->id;
        $customer_thread->email = $context->customer->email;
        $customer_thread->status = 'open';
        $customer_thread->token = Tools::passwdGen(12);
        $customer_thread->add();

        $customer_message = new CustomerMessage();
        $customer_message->id_customer_thread = $customer_thread->id;
        $customer_message->id_employee = 0;
        $customer_message->message = $message;
        $customer_message->private = 1;

        $customer_message->add();

        $subscription->setCustomerMessage(null);

        $this->entityManager->flush();
    }
}
