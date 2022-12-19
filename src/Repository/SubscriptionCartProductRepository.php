<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Repository;

use Doctrine\ORM\EntityRepository;

class SubscriptionCartProductRepository extends EntityRepository
{
    /**
     * @param int $cartId
     *
     * @return array
     */
    public function findAllActiveByCartId(int $cartId): array
    {
        $qb = $this->createQueryBuilder('subscriptionCartProduct');
        $expr = $qb->expr();

        return $qb
            ->addSelect('subscriptionPeriodicity')
            ->innerJoin('subscriptionCartProduct.periodicity', 'subscriptionPeriodicity')
            ->where($expr->eq('subscriptionCartProduct.cartId', ':cartId'))
            ->andWhere($expr->eq('subscriptionCartProduct.isActive', true))
            ->setParameter('cartId', $cartId)
            ->getQuery()
            ->getResult();
    }
}
