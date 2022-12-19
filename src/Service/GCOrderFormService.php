<?php

declare(strict_types=1);

namespace BelVG\ProductSubscription\Service;

use BelVG\ProductSubscription\ReadModel\SubscriptionAvailabilityReadModel;
use Category;
use Context;
use GcOrderForm;
use Group;
use Product;
use Tools;

class GCOrderFormService
{
    private const PRODUCT_LIMIT = 1000;
    
    /**
     * @var SubscriptionAvailabilityReadModel
     */
    private $availabilityReadModel;

    /**
     * @param SubscriptionAvailabilityReadModel $availabilityReadModel
     */
    public function __construct(SubscriptionAvailabilityReadModel $availabilityReadModel)
    {
        $this->availabilityReadModel = $availabilityReadModel;
    }

    /**
     * @param array $categoryIds
     * 
     * @return array
     */
    public function toGCOrderFormFormat(array $categoryIds): array
    {
        $context = Context::getContext();
        $gcOrderFormCategories = [];

        foreach ($categoryIds as $categoryId) {
            $gcofCategory = new Category($categoryId, $context->language->id);

            if ($gcofCategory->checkAccess((int) $context->customer->id)) {
                $gcOrderFormCategories[] = ['id_category' => $gcofCategory->id, 'name' => $gcofCategory->name, 'link_rewrite' => $gcofCategory->link_rewrite];
            }
        }

        return $gcOrderFormCategories;
    }

    /**
     * @param GcOrderForm $gcOrderForm
     * @param bool $onlyStock
     * @param int|null $categoryId
     *
     * @return array
     */
    public function getAvailableProductsOfCategory(GcOrderForm $gcOrderForm, bool $onlyStock, int $categoryId = null): array
    {
        $context = Context::getContext();
        $customer = (int) $context->customer->id;
        $langId = $context->language->id;
        $specific_price_output = null;
        
        if (Group::getPriceDisplayMethod($context->customer->id_default_group) == 1) {
            $usetax = false;
        } else {
            $usetax = true;
        }

        if (null === $categoryId) {
            $products = Product::getProducts($langId, 0, self::PRODUCT_LIMIT, 'name', 'asc', false, true, $context);
        } else {
            $products = Product::getProducts($langId, 0, self::PRODUCT_LIMIT, 'name', 'asc', $categoryId, true, $context);
        }

        $availableProductIds = $this->availabilityReadModel->getAvailableProductIds();
        $filteredProducts = array_filter($products, function ($item) use($availableProductIds) {
            return in_array($item['id_product'], $availableProductIds, true);
        });

        $gcOrderFormProducts = [];

        foreach ($filteredProducts as $gcOrderFormProduct) {
            $gcOrderFormProductObject = new Product($gcOrderFormProduct['id_product'], true, $langId);
            $gcOrderFormProduct['link'] = $context->link->getProductLink($gcOrderFormProductObject, $gcOrderFormProductObject->link_rewrite, null, null, $context->cookie->id_lang, $context->shop->id, Product::getDefaultAttribute($gcOrderFormProduct['id_product']));
            $gcOrderFormProduct['declinaison'] = $gcOrderForm::getDecliOfProduct($gcOrderFormProduct['id_product']);
            $gcOrderFormProduct['price'] = Tools::displayPrice(Product::getPriceStatic($gcOrderFormProduct['id_product'], $usetax, null, 2, null, false, true, 1, false, $customer, null, null, $specific_price_output, true, true, $context, true));
			$gcOrderFormProduct['price_without_reduction'] = Tools::displayPrice(Product::getPriceStatic($gcOrderFormProduct['id_product'], $usetax, null, 2, null, false, false, 1, false, $customer, null, null, $specific_price_output, true, true, $context, true));
			$gcOrderFormProduct['quantityavailable'] = Product::getQuantity($gcOrderFormProduct['id_product']);
			$gcOrderFormProduct['specific_prices'] = $specific_price_output;

            if (true === $onlyStock) {
                if ($gcOrderFormProduct['quantityavailable'] > 0) {
                    $gcOrderFormProducts[] = $gcOrderFormProduct;
                }
            } else {
                $gcOrderFormProducts[] = $gcOrderFormProduct;
            }
        }

        return $gcOrderFormProducts;
    }
}
