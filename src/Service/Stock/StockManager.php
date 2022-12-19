<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Service\Stock;

use PrestaShop\PrestaShop\Core\Stock\StockManager as StockManagerCore;


class StockManager extends StockManagerCore
{
    public function saveMovement($productId, $productAttributeId, $deltaQuantity, $params = [])
    {
        $context = \Context::getContext();
        $cookie = $context->cookie->getAll();
        if (isset($cookie['origin_subscription_id'])) {
            return false;
        }


        return parent::saveMovement($productId, $productAttributeId, $deltaQuantity, $params);
    }

}