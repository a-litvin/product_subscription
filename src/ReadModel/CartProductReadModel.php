<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\ReadModel;

class CartProductReadModel extends AbstractReadModel
{
    /**
     * @param int $cartId
     *
     * @return array
     */
    public function getCartProductsByCartId(int $cartId): array
    {
        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();

        $products = $qb->select('cartProduct.id_product, cartProduct.id_product_attribute, cartProduct.quantity')
            ->from($this->databasePrefix . 'cart_product', 'cartProduct')
            ->where($expr->eq('cartProduct.id_cart', ':cartId'))
            ->setParameter('cartId', $cartId)
            ->orderBy('cartProduct.id_product')
            ->addOrderBy('cartProduct.id_product_attribute')
            ->execute()
            ->fetchAll();

        if (empty($products)) {
            return $products;
        }

        $structuredData = [];
        
        foreach ($products as $product) {
            $structuredData[$product['id_product']][$product['id_product_attribute']] = (int) $product['quantity'];
        }

        return $structuredData;
    }
}
