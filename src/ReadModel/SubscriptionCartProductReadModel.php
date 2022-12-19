<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\ReadModel;

class SubscriptionCartProductReadModel extends AbstractReadModel
{
    /**
     * @param int $cartId
     *
     * @return array
     */
    public function getDistinctActivePeriodicityIdsByCartId(int $cartId): array
    {
        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();

        return $qb->select('DISTINCT subscription_cart_product.id_periodicity')
            ->from($this->databasePrefix . 'subscription_cart_product', 'subscription_cart_product')
            ->where($expr->eq('subscription_cart_product.id_cart', ':cartId'))
            ->andWhere($expr->eq('subscription_cart_product.is_active', '1'))
            ->setParameter('cartId', $cartId)
            ->orderBy('subscription_cart_product.id_periodicity')
            ->execute()
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @param int $cartId
     *
     * @return array
     */
    public function getActiveIdsByCartId(int $cartId): array
    {
        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();

        return $qb->select('subscription_cart_product.id_subscription_cart_product')
            ->from($this->databasePrefix . 'subscription_cart_product', 'subscription_cart_product')
            ->where($expr->eq('subscription_cart_product.id_cart', ':cartId'))
            ->andWhere($expr->eq('subscription_cart_product.is_active', '1'))
            ->setParameter('cartId', $cartId)
            ->orderBy('subscription_cart_product.id_subscription_cart_product')
            ->execute()
            ->fetchAll(\PDO::FETCH_COLUMN);
    }
}
