<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\ReadModel;

use Doctrine\DBAL\Types\Type;

class SubscriptionReadModel extends AbstractReadModel
{
    /**
     * @param \DateTimeImmutable $date
     *
     * @return array
     */
    public function getActiveIdsByDateDelivery(\DateTimeImmutable $date): array
    {
        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();

        return $qb
            ->select('subscription.id_subscription')
            ->from($this->databasePrefix . 'subscription', 'subscription')
            ->where($expr->eq('subscription.is_active', ':isActive'))
            ->andWhere($expr->eq('subscription.is_deleted', ':isDeleted'))
            ->andWhere($expr->lte('subscription.next_delivery', ':nextDelivery'))
            ->setParameter('isActive', true, Type::BOOLEAN)
            ->setParameter('isDeleted', false, Type::BOOLEAN)
            ->setParameter('nextDelivery', $date, Type::DATETIME_IMMUTABLE)
            ->orderBy('subscription.id_subscription', 'DESC')
            ->execute()
            ->fetchAll(\PDO::FETCH_COLUMN);
    }
    /**
     * @param \DateTimeImmutable $date
     *
     * @return array
     */
    public function getAllIdsByDateDelivery(\DateTimeImmutable $date): array
    {
        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();

        return $qb
            ->select('subscription.id_subscription')
            ->from($this->databasePrefix . 'subscription', 'subscription')
            ->andWhere($expr->eq('subscription.is_deleted', ':isDeleted'))
            ->andWhere($expr->lte('subscription.next_delivery', ':nextDelivery'))
            ->setParameter('isDeleted', false, Type::BOOLEAN)
            ->setParameter('nextDelivery', $date, Type::DATETIME_IMMUTABLE)
            ->orderBy('subscription.id_subscription', 'DESC')
            ->execute()
            ->fetchAll(\PDO::FETCH_COLUMN);
    }
}
