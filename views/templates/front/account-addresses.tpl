<div class="productsubscription-popup-content">
    <form
            id="productsubscription-account-form-modal"
            method="post"
            action="{url entity='module' name='productsubscription' controller='account'}"
            data-action="selectShippingAddress"
            data-id-subscription="{$id_subscription}"
    >
    {foreach from=$addresses item=address}
        {assign var=selected value=$address.id_address == $shippingAddressId}
        <div class="">
            <div class="card-header">
                {if $selected}
                    {l s='Selected address' mod='productsubscription'}:
                {else}
                    {l s='Alternative address' mod='productsubscription'}:
                {/if}
            </div>
            <div class="card-body">
                {$address.firstname} {$address.lastname}
                {$address.phone}{if !empty($address.phone_mobile)} (mob. {$address.phone_mobile}){/if}<br>
                {$address.address1}{if !empty($address.address2)}<br>{$address.address2}{/if}<br>
                {$address.city}{if !empty($address.state)}, {$address.state}{/if}<br>
                {$address.postcode}<br>
                {$address.country}
                <div>
                    <input type="radio" name="shipping-address-id" value="{$address.id_address}" {if $selected} checked{/if}>
                </div>
            </div>

        </div>
    {/foreach}
        {if $addresses|@count > 0}
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Submit</button>
            </div>
        {/if}
        <a
            href="{url entity='module' name='productsubscription' controller='account'}"
            data-action="displayNewAddressForm"
            data-trigger="click"
        >
            {l s='Create new address' mod='productsubscription'}
        </a>
    </form>
</div>