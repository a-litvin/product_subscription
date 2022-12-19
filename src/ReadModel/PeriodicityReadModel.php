<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\ReadModel;

class PeriodicityReadModel extends AbstractReadModel
{
    /**
     * @return array
     */
    public function getAll(): array
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('periodicity.id_periodicity, periodicity.interval, periodicity.name, periodicity.created_at, periodicity.updated_at')
            ->from($this->databasePrefix . 'subscription_periodicity', 'periodicity')
            ->orderBy('periodicity.interval');

        return $qb->execute()->fetchAll();
    }
}
