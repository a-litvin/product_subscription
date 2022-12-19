{if $periodicities|count}
    <div class="productsubscription_periodicity_dropdown">
        <span class="form-control-label">Subscription</span>
        <div class="row">
            <div class="col-md-6">
                <select
                        class="form-control form-control-select"
                        data-url="{url entity='module' name='productsubscription' controller='ajax'}"
                        data-cart_id="{$cartId}"
                        data-product_id="{$productId}"
                        data-product_attribute_id="{$productAttributeId}"
                        data-action="product"
                        name="{$selectName}"
                >
                    <option value="0">{l s='Deliver once' d='Modules.BelVGProductSubscription'}</option>
                    {foreach from=$periodicities item=periodicity}
                        <option value="{$periodicity.id_periodicity}" {if $periodicity.id_periodicity == $selectedPeriodicityId} selected="selected"{/if}>{$periodicity.name}</option>
                    {/foreach}
                </select>
            </div>
        </div>
    </div>
    <br>
{/if}