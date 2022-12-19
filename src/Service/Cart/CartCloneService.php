<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Service\Cart;

use BelVG\ProductSubscription\DTO\ProductDTO;
use Cart;
use Shop;

class CartCloneService
{
    /**
     * @param int $cartId
     *
     * @return Cart
     */
    public function getCartClone(int $cartId): Cart
    {
        $cart = new Cart($cartId);
        $cart->id = null;
        $cart->checkout_session_data = null;
        $cart->add();

        return $cart;
    }

    /**
     * @param Cart $cart
     * @param ProductDTO $productDTO
     */
    public function updateCartProducts(Cart $cart, ProductDTO $productDTO): void
    {
        $cart->updateQty(
            $productDTO->quantity,
            $productDTO->productId,
            $productDTO->productAttributeId,
            0,
            'up',
            (int) $cart->id_address_delivery,
            new Shop((int) $cart->id_shop),
            false,
            true
        );
    }
}
