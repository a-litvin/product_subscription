<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\DTO;

class ProductDTO
{
    /**
     * @var int
     */
    public $productId;

    /**
     * @var int
     */
    public $productAttributeId;

    /**
     * @var int
     */
    public $quantity;

    /**
     * @param int $productId
     * @param int $productAttributeId
     * @param int $quantity
     */
    public function __construct(int $productId, int $productAttributeId, int $quantity)
    {
        $this->productId = $productId;
        $this->productAttributeId = $productAttributeId;
        $this->quantity = $quantity;
    }

    /**
     * @param array $product
     *
     * @return static
     */
    public static function createFromPrestashopProduct(array $product): self
    {
        return new self(
            (int) $product['id_product'],
            (int) $product['id_product_attribute'],
            (int) $product['cart_quantity']
        );
    }
}
