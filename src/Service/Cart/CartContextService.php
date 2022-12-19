<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Service\Cart;

use BelVG\ProductSubscription\Entity\Subscription;
use Cart;
use Context;

class CartContextService
{
    /**
     * @var Context|null
     */
    private $context;

    /**
     * @param Context|null $context
     */
    public function __construct(Context $context = null)
    {
        $this->context = $context ?? Context::getContext();
    }

    /**
     * @return bool
     */
    public function isShipNow(): bool
    {
        return isset($this->context->cookie->subscription_copy_cart_id) && isset($this->context->cookie->origin_subscription_id);
    }

    /**
     * @param int $cartId
     *
     * @return bool
     */
    public function isShipNowCart(int $cartId): bool
    {
        if (!isset($this->context->cookie->subscription_copy_cart_id)) {
            return false;
        }

        return $cartId === (int) $this->context->cookie->subscription_copy_cart_id;
    }

    /**
     * Restoring cart context.
     */
    public function restoreCartContext(): void
    {
        if (isset($this->context->cookie->context_cart_id)) {

            if (!empty($this->context->cookie->context_cart_id)) {
                $currentCartId = $this->context->cookie->context_cart_id;
                $this->context->cookie->id_cart = $currentCartId;
                $currentCart = new Cart($currentCartId);
                $this->context->cart = $currentCart;
            }

            unset($this->context->cookie->context_cart_id);
        }

        if (isset($this->context->cookie->subscription_copy_cart_id)) {

            if (!empty($this->context->cookie->subscription_copy_cart_id)) {
                $subscriptionCartId = $this->context->cookie->subscription_copy_cart_id;
                $subscriptionCart = new Cart($subscriptionCartId);
                $subscriptionCart->delete();
            }

            unset($this->context->cookie->subscription_copy_cart_id);
        }

        if (isset($this->context->cookie->origin_subscription_id)) {
            unset($this->context->cookie->origin_subscription_id);
        }
    }

    /**
     * @param Cart $newCart
     */
    public function replaceCartContext(Cart $newCart): void
    {
        if (isset($this->context->cart->id) && !empty($this->context->cart->id)) {
            $this->context->cookie->context_cart_id = $this->context->cart->id;
        }

        $this->context->cart = $newCart;
        $this->context->cookie->id_cart = $newCart->id;
    }

    /**
     * @param Subscription $subscription
     */
    public function setSubscriptionToCookie(Subscription $subscription): void
    {
        $this->context->cookie->origin_subscription_id = $subscription->getId();
    }

    /**
     * @param int $newCartId
     */
    public function setSubscriptionCopyCartToCookie(int $newCartId): void
    {
        $this->context->cookie->subscription_copy_cart_id = $newCartId;
    }

    /**
     * Cookies clearing.
     */
    public function clearCookie(): void
    {
        if (isset($this->context->cookie->context_cart_id)) {
            unset($this->context->cookie->context_cart_id);
        }

        if (isset($this->context->cookie->subscription_copy_cart_id)) {
            unset($this->context->cookie->subscription_copy_cart_id);
        }

        if (isset($this->context->cookie->origin_subscription_id)) {
            unset($this->context->cookie->origin_subscription_id);
        }
    }

    /**
     * @return int|null
     */
    public function getCopySubscriptionCartId(): ?int
    {
        if (isset($this->context->cookie->subscription_copy_cart_id) && !empty($this->context->cookie->subscription_copy_cart_id)) {
            return (int) $this->context->cookie->subscription_copy_cart_id;
        }

        return null;
    }

    /**
     * @return int|null
     */
    public function getOriginSubscriptionId(): ?int
    {
        if (isset($this->context->cookie->origin_subscription_id) && !empty($this->context->cookie->origin_subscription_id)) {
            return (int) $this->context->cookie->origin_subscription_id;
        }

        return null;
    }
}
