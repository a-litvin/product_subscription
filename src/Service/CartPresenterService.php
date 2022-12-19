<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Service;

use Cart;
use PrestaShop\PrestaShop\Adapter\Presenter\Cart\CartPresenter;

class CartPresenterService
{
    /**
     * @var float
     */
    private $shippingAmount;

    /*
     * @var bool
     */
    private $shippingFree;

    /**
     * @var float
     */
    private $productsAmount;

    /**
     * @var float
     */
    private $totalAmountTaxExcluded;

    /**
     * @var float
     */
    private $totalAmount;

    /**
     * @var CartPresenter
     */
    private $presenter;

    public function __construct()
    {
        $this->presenter = new CartPresenter();
    }

    /**
     * @param Cart $cart
     */
    public function calculate(Cart $cart): void
    {
        $presentedCart = $this->presenter->present($cart);
        $this->shippingAmount = (float) $presentedCart['subtotals']['shipping']['amount'];
		$this->shippingFree = ($presentedCart['subtotals']['shipping']['value'] == 'Free') ? true : false;
        $this->productsAmount = (float) $presentedCart['subtotals']['products']['amount'];
        $this->totalAmount = (float) $presentedCart['totals']['total_including_tax']['amount'];
        $this->totalAmountTaxExcluded = (float) $presentedCart['totals']['total_excluding_tax']['amount'];
    }

    /**
     * @return float
     */
    public function getShippingAmount(): float
    {
        return $this->shippingAmount;
    }

	/**
	 * @return bool
	 */
	public function getShippingFree(): bool
	{
		return $this->shippingFree;
	}

    /**
     * @return float
     */
    public function getProductsAmount(): float
    {
        return $this->productsAmount;
    }

    /**
     * @return float
     */
    public function getTotalAmountTaxExcluded(): float
    {
        return $this->totalAmountTaxExcluded;
    }

    /**
     * @return float
     */
    public function getTotalAmount(): float
    {
        return $this->totalAmount;
    }
}
