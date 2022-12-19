<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Repository;

use BelVG\ProductSubscription\Entity\Subscription;
use BelVG\ProductSubscription\Entity\SubscriptionProduct;
use Doctrine\ORM\EntityRepository;

class SubscriptionProductRepository extends EntityRepository
{
    /**
     * @param Subscription $subscription
     * @param int $productId
     * @param int $productAttributeId
     *
     * @return SubscriptionProduct
     */
    public function getOrCreate(Subscription $subscription, int $productId, int $productAttributeId): SubscriptionProduct
    {
        $subscriptionProduct = $this->findOneBy([
            'subscription' => $subscription,
            'productId' => $productId,
            'productAttributeId' => $productAttributeId,
        ]);

        if (null !== $subscriptionProduct) {
            return $subscriptionProduct;
        }

        $subscriptionProduct = new SubscriptionProduct($subscription, $productId, $productAttributeId);

        $this->_em->persist($subscriptionProduct);
        $this->_em->flush();

        return $subscriptionProduct;
    }
}
