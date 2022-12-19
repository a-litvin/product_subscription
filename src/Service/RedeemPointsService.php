<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Service;

use BelVG\ProductSubscription\Entity\Subscription;
use Cart;
use Configuration;
use Context;
use LRPCartModel;
use LRPCustomerModel;
use LRPDiscountHelper;
use PrestaShop\PrestaShop\Core\Domain\Exception\DomainException;

class RedeemPointsService
{
    /**
     * @var Context
     */
    private $context;

    /**
     * @param Context|null $context
     */
    public function __construct(Context $context = null)
    {
        $this->context = $context ?? Context::getContext();
    }

    /**
     * @param int $redeemPoints
     * @param Cart $cart
     *
     * @return int
     *
     * @throws DomainException
     */
    public function clearRedeemPoints(int $redeemPoints, Cart $cart): int
    {
        $customerId = (int) $cart->id_customer;
        $lrpCustomer = new LRPCustomerModel();
        $lrpCustomer->loadByCustomerID($customerId);

        if ($redeemPoints <= 0) {
            $redeemPoints = 0;
        }

        if ($redeemPoints > $lrpCustomer->points) {
            $redeemPoints = $lrpCustomer->points;
        }

        $cartTotal = $cart->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING);

        $minPointsRedemption = Configuration::get('lrp_min_points_redemption_' . $this->context->currency->iso_code);

        if ($minPointsRedemption > 0 && $redeemPoints < $minPointsRedemption) {
            throw new DomainException(sprintf('You must have at least %s points before you redeem', $minPointsRedemption));
        }

        $redeemPointsValue = LRPDiscountHelper::getPointsMoneyValue($redeemPoints, $this->context->currency->iso_code);

        if ($redeemPointsValue > $cartTotal) {
            $redeemPoints = LRPDiscountHelper::getMoneyPointsValue($cartTotal, $this->context->currency->iso_code);
        }

        return (int) $redeemPoints;
    }

    /**
     * @param int $redeemPoints
     * @param Cart $cart
     */
    public function setPointsRedeem(int $redeemPoints, Cart $cart): void
    {
        $customerId = (int) $cart->id_customer;
        $cartId = (int) $cart->id;

        $lrpCart = new LRPCartModel();
        $lrpCart->load($cartId, $customerId);
        $lrpCart->points_redeemed = $redeemPoints;
        $lrpCart->id_cart = $cartId;
        $lrpCart->id_customer = $customerId;
        $lrpCart->save();
    }

    /**
     * @param Subscription $subscription
     *
     * @return bool
     */
    public function isRedeemPointsApplied(Subscription $subscription): bool
    {
        $lrpCart = new LRPCartModel();
        $lrpCart->load($subscription->getCartId(), $subscription->getCustomerId());

        return (int) $lrpCart->points_redeemed > 0;
    }

	/**
	 * @param Subscription $subscription
	 *
	 * @return int
	 */
	public function getRedeemPointsApplied(Subscription $subscription): int
	{
		$lrpCart = new LRPCartModel();
		$lrpCart->load($subscription->getCartId(), $subscription->getCustomerId());

		return (int) $lrpCart->points_redeemed;
	}

    /**
     * @param Cart $sourceCart
     * @param Cart $targetCart
     */
    public function copyRedeemPoints(Cart $sourceCart, Cart $targetCart): void
    {
        $lrpSourceCart = new LRPCartModel();
        $lrpSourceCart->load($sourceCart->id, $sourceCart->id_customer);
        $pointsRedeemed = (int) $lrpSourceCart->points_redeemed;

        if (0 === $pointsRedeemed) {
            return;
        }

        try{
            $pointsRedeemed = $this->clearRedeemPoints($pointsRedeemed, $sourceCart);
        } catch (DomainException $exception) {
            return;
        }

        $this->setPointsRedeem($pointsRedeemed, $targetCart);
    }

	/**
	 * @param Subscription $subscription
	 * @return int
	 */
	public function getCustomerPoints(Subscription $subscription): int
	{
		$lrp_customer = new LRPCustomerModel();
		$lrp_customer->loadByCustomerID($subscription->getCustomerId());

		if (!empty($lrp_customer->id_customer)) {
			$points = $lrp_customer->points;
		} else {
			$points = 0;
		}
		return (int) $points;
	}
}
