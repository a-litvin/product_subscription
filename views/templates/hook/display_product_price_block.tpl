{if $periodicities|count}
    <div class="productsubscription_periodicity_dropdown">
        <div>
            <label>
                <input
                    data-url="{url entity='module' name='productsubscription' controller='ajax'}"
                    data-cart_id="{$cartId}"
                    data-product_id="{$productId}"
                    data-product_attribute_id="{$productAttributeId}"
                    data-action="product"
                    type="radio"
                    name="productsubscription-product-price"
                    value="0"
                    {if !$showPeriodicityBlock}checked{/if}
                > One-time purchase
            </label>
        </div>
        <div>
            <label>
                <input
                    data-url="{url entity='module' name='productsubscription' controller='ajax'}"
                    data-cart_id="{$cartId}"
                    data-product_id="{$productId}"
                    data-product_attribute_id="{$productAttributeId}"
                    data-action="product"
                    type="radio"
                    name="productsubscription-product-price"
                    value="{$firstPeriodicityId}"
                    {if $showPeriodicityBlock}checked{/if}
                > Subscribe & get Discount
            </label>
        </div>
        <div {if !$showPeriodicityBlock}style="display: none" {/if}>
            <div class="row">
                <div class="col-md-4">
                    <select
                            class="form-control form-control-select"
                            data-url="{url entity='module' name='productsubscription' controller='ajax'}"
                            data-cart_id="{$cartId}"
                            data-product_id="{$productId}"
                            data-product_attribute_id="{$productAttributeId}"
                            data-action="product"
                            name="{$selectName}"
                    >
                        {foreach from=$periodicities item=periodicity}
                            <option value="{$periodicity.id_periodicity}" {if $periodicity.id_periodicity == $selectedPeriodicityId} selected="selected"{/if}>{$periodicity.name}</option>
                        {/foreach}
                    </select>
                </div>
            </div>
        </div>
    </div>
{/if}