<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\ReadModel;

class ProductReadModel extends AbstractReadModel
{
    /**
     * @param int $productId
     * @param int $productAttributeId
     *
     * @return bool
     */
    public function isCombinationExist(int $productId, int $productAttributeId): bool
    {
        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();

        $result = $qb->select('product_attribute.id_product_attribute')
            ->from($this->databasePrefix . 'product_attribute', 'product_attribute')
            ->where($expr->eq('product_attribute.id_product', ':productId'))
            ->andWhere($expr->eq('product_attribute.id_product_attribute', ':productAttributeId'))
            ->setParameters([
                'productId' => $productId,
                'productAttributeId' => $productAttributeId,
            ])
            ->execute()
            ->fetchColumn();

        return $result ? true : false;
    }
}
