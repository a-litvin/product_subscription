<div class="delivery-options-list">
    {if $delivery_options|count}
        <form
                id="productsubscription-account-form-modal"
                method="post"
                action="{url entity='module' name='productsubscription' controller='account'}"
                data-action="selectDelivery"
                data-id-subscription="{$id_subscription}"
                class="clearfix"
        >
            <div class="form-fields">
                {block name='delivery_options'}
                    <div class="delivery-options">
                        {foreach from=$delivery_options item=carrier key=carrier_id}
                            {assign var=carrier_id value=$carrier_id|intval}
                            <div class="row delivery-option">
                                <div class="col-sm-1">
                                  <span class="custom-radio float-xs-left">
                                    <input type="radio" name="delivery_option" id="delivery_option_{$carrier_id}"
                                           value="{$carrier_id}"{if $delivery_option == $carrier_id} checked{/if}>
                                    <span></span>
                                  </span>
                                </div>
                                <label for="delivery_option_{$carrier_id}" class="col-sm-11 delivery-option-2">
                                    <div class="row">
                                        <div class="col-sm-5 col-xs-12">
                                            <div class="row">
                                                {if $carrier.carrier_list[$carrier_id].logo}
                                                    <div class="col-xs-3">
                                                        <img src="{$carrier.carrier_list[$carrier_id].logo}" alt="{$carrier.carrier_list[$carrier_id].instance->name}"/>
                                                    </div>
                                                {/if}
                                                <div class="{if $carrier.carrier_list[$carrier_id].logo}col-xs-9{else}col-xs-12{/if}">
                                                    <span class="h6 carrier-name">{$carrier.carrier_list[$carrier_id].instance->name}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-4 col-xs-12">
                                            <span class="carrier-delay">{$carrier.carrier_list[$carrier_id].instance->delay[$id_language]}</span>
                                        </div>
                                        <div class="col-sm-3 col-xs-12">
                                            <span class="carrier-price">{$carrier.total_price_with_tax}</span>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <div class="clearfix"></div>
                        {/foreach}
                    </div>
                {/block}
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg mt-3" name="confirmDeliveryOption"
                    value="1">
                {l s='Continue' d='Shop.Theme.Actions'}
            </button>
        </form>
    {else}
        <p class="alert alert-danger">{l s='Unfortunately, there are no carriers available for your delivery address.' d='Shop.Theme.Checkout'}</p>
    {/if}
</div>