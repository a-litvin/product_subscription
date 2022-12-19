<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\ReadModel;

use Cart;

class CarrierReadModel extends AbstractReadModel
{
    /**
     * @param string $name
     *
     * @return int|null
     */
    public function getCarrierReferenceIdByName(string $name): ?int
    {
        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();

        $result = $qb->select('carrier.id_reference')
            ->from($this->databasePrefix . 'carrier', 'carrier')
            ->where($expr->eq('carrier.name', ':name'))
            ->andWhere($expr->eq('carrier.deleted', 0))
            ->setParameter('name', $name)
            ->setMaxResults(1)
            ->execute()
            ->fetchColumn();

        return $result ? (int) $result : null;
    }
}
