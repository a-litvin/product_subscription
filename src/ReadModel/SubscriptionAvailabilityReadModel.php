<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\ReadModel;

class SubscriptionAvailabilityReadModel extends AbstractReadModel
{
    /**
     * @return array
     */
    public function getAvailableProductIds(): array
    {
        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();

        return $qb->select('subscription_availability.id_product')
            ->from($this->databasePrefix . 'subscription_availability', 'subscription_availability')
            ->where($expr->eq('subscription_availability.is_available', '1'))
            ->orderBy('subscription_availability.id_product')
            ->execute()
            ->fetchAll(\PDO::FETCH_COLUMN);
    }
}
