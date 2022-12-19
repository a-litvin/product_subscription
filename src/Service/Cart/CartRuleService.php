<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Service\Cart;

use BelVG\ProductSubscription\ReadModel\AbstractReadModel;
use Cart;

class CartRuleService extends AbstractReadModel
{
    /**
     * @param int $cartId
     * @param int $ruleId
     *
     * @return bool
     */
    public function cartHasCartRule(int $cartId): bool
    {
        $result = $this->getCartRuleIds($cartId);

        return !empty($result);
    }

    /**
     * @param int $cartId
     *
     * @return array
     */
    public function getCartRuleIds(int $cartId): array
    {
        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();

        return $qb->select('cart_cart_rule.id_cart_rule')
            ->from($this->databasePrefix . 'cart_cart_rule', 'cart_cart_rule')
            ->where($expr->eq('cart_cart_rule.id_cart', ':cartId'))
            ->setParameter('cartId', $cartId)
            ->execute()
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @param Cart $sourceCart
     * @param Cart $targetCart
     */
    public function copyCartRules(Cart $sourceCart, Cart $targetCart): void
    {
        $cartRuleIds = $this->getCartRuleIds($sourceCart->id);

        foreach ($cartRuleIds as $cartRuleId) {
            $targetCart->addCartRule($cartRuleId);
        }
    }

    /**
     * @param Cart $sourceCart
     * @param Cart $targetCart
     */
    public function moveCartRules(Cart $sourceCart, Cart $targetCart): void
    {
        $this->copyCartRules($sourceCart, $targetCart);
        $this->removeCartRules($sourceCart);
    }

    /**
     * @param Cart $cart
     */
    public function removeCartRules(Cart $cart): void
    {
        $cartRuleIds = $this->getCartRuleIds($cart->id);

        foreach ($cartRuleIds as $cartRuleId) {
            $cart->removeCartRule($cartRuleId);
        }
    }
}
