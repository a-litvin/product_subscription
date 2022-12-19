<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Service;

use BelVG\ProductSubscription\Service\Cart\CartContextService;
use Cart;
use Context;
use Db;
use DbQuery;
use Tools;

class SubscriptionAvailabilityService
{
    /**
     * @param bool $forceDiscount
     */
    public static function setForceDiscount(bool $forceDiscount): void
    {
        self::$forceDiscount = $forceDiscount;
    }

    /**
     * @var bool
     */
    private static $forceDiscount = false;

    /**
     * @var array
     */
    private static $contextCartCache = [];

    /**
     * @var array
     */
    private static $subscriptionCartCache = [];

    /**
     * @var array
     */
    private static $availableForSubscriptionCache = [];

    /**
     * @param int $productId
     *
     * @return bool
     */
    public static function isAvailableForSubscription(int $productId): bool
    {
        $key = $productId;

        if (isset(self::$availableForSubscriptionCache[$key])) {
            return self::$availableForSubscriptionCache[$key];
        }

        $sql = new DbQuery();
        $sql->select('id_availability');
        $sql->from('subscription_availability', 'subscription_availability');
        $sql->where('subscription_availability.id_product = ' . $productId);

        self::$availableForSubscriptionCache[$key] = !empty(Db::getInstance()->executeS($sql));

        return self::$availableForSubscriptionCache[$key];
    }

    /**
     * @param int $productId
     * @param int $productAttributeId
     * @param int $cartId
     *
     * @return bool
     */
    public static function contextCartHasSubscriptionProduct(int $productId, int $productAttributeId, int $cartId): bool
    {
        $key = sprintf('%d-%d-%d', $cartId, $productId, $productAttributeId);

        if (isset(self::$contextCartCache[$key])) {
            return self::$contextCartCache[$key];
        }

        $sql = new DbQuery();
        $sql->select('id_subscription_cart_product');
        $sql->from('subscription_cart_product', 'subscription_cart_product');
        $sql->where(
            'subscription_cart_product.id_product = ' . $productId .
            ' AND subscription_cart_product.id_cart = ' . $cartId .
            ' AND subscription_cart_product.id_product_attribute = ' . $productAttributeId
        );

        self::$contextCartCache[$key] = !empty(Db::getInstance()->executeS($sql, true, false));

        return self::$contextCartCache[$key];
    }

    /**
     * @param int $productId
     * @param int $productAttributeId
     * @param int $cartId
     *
     * @return bool
     */
    public static function cartHasProduct(int $productId, int $productAttributeId, int $cartId): bool
    {
        if (0 === $cartId) {
            return false;
        }

        $cart = new Cart($cartId);

        $productQuantity = $cart->getProductQuantity($productId, $productAttributeId);

        if (false !== $productQuantity && isset($productQuantity['quantity']) && $productQuantity['quantity'] > 0) {
            return true;
        }

        return false;
    }

    /**
     * @param int $cartId
     *
     * @return bool
     */
    public static function isSubscriptionCart(int $cartId): bool
    {
        $key = $cartId;

        if (isset(self::$subscriptionCartCache[$key])) {
            return self::$subscriptionCartCache[$key];
        }

        $sql = new DbQuery();
        $sql->select('id_subscription');
        $sql->from('subscription', 'subscription');
        $sql->where('subscription.id_cart = ' . $cartId);

        self::$subscriptionCartCache[$key] = !empty(Db::getInstance()->executeS($sql, true, false));

        return self::$subscriptionCartCache[$key];
    }

    /**
     * @param int $cartId
     *
     * @return bool
     */
    public static function isShipNowCart(int $cartId): bool
    {
        $cartContextService = new CartContextService();

        if (!$cartContextService->isShipNow()) {
            return false;
        }

        return $cartContextService->isShipNowCart($cartId);
    }

    /**
     * @param int $productId
     * @param int $productAttributeId
     * @param int $cartId
     *
     * @return bool
     */
    public static function isSubscriptionDiscountApplicable(int $productId, int $productAttributeId, int $cartId): bool
    {
        if (true === self::$forceDiscount) {
            return true;
        }

        if (0 === $cartId) {
            $cart = Context::getContext()->cart;

            if (null === $cart) {
                return false;
            }

            $cartId = (int) $cart->id;

            if (0 !== $cartId) {
                return self::contextCartHasSubscriptionProduct($productId, $productAttributeId, $cartId);
            }
        }

        if (null !== Context::getContext()->cart && $cartId === (int) Context::getContext()->cart->id && !self::isSubscriptionCart($cartId) && !self::isShipNowCart($cartId)) {
            return self::contextCartHasSubscriptionProduct($productId, $productAttributeId, $cartId);
        }

        if (self::isSubscriptionCart($cartId)) {
            return true;
        }

        return self::isShipNowCart($cartId);
    }
}
