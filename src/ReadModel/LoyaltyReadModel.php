<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\ReadModel;

class LoyaltyReadModel extends AbstractReadModel
{
    /**
     * @param array $customerIds
     */
    public function clearAllPoints(array $customerIds): void
    {
        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();
        $time = date('Y-m-d H:i:s');

        $qb->update($this->databasePrefix . 'lrp_customer', 'lrp_customer')
            ->set('lrp_customer.points', 0)
            ->set('lrp_customer.date_upd', ':time')
            ->where($expr->in('lrp_customer.id_customer', $customerIds))
            ->setParameter('time', $time)
            ->execute();
    }

    /**
     * @param int $customerId
     * @param float $points
     */
    public function addPoints(int $customerId, float $points): void
    {
        $qb = $this->connection->createQueryBuilder();
        $time = date('Y-m-d H:i:s');

        $qb->insert($this->databasePrefix . 'lrp_customer')
            ->setValue('id_customer', ':customerId')
            ->setValue('points', ':points')
            ->setValue('date_add', ':time')
            ->setValue('date_upd', ':time')
            ->setParameters([
                'customerId' => $customerId,
                'points' => $points,
                'time' => $time,
            ])
            ->execute();
    }
}
