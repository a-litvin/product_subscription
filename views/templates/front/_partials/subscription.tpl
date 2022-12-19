{block name="subscription"}
    {if isset($index) }
        {assign var=autoshipNumber value=$index+1}
    {/if}

    {assign var=id_subscription value=$subscriptionData.subscription->getId()}
    <div id="subscription-wrapper-{$id_subscription}">
        <form
            method="post" action="{url entity='module'
            name='productsubscription' controller='account'}"
            data-id-subscription="{$id_subscription}"
        >
            {block name="subscription_header"}
                <div class="account-subscription-header form-inline justify-content-between">
                    <h3
                            class="form-group account-subscription-name"
                            id="account-subscription-name-{$id_subscription}"
                    >
                        {if $subscriptionData.subscription->getName()}
                            {$subscriptionData.subscription->getName()}
                        {else}
                            Autoship {$autoshipNumber}
                        {/if}
                        <span id="productsubscription-account-subscription-status-{$id_subscription}">
                            {if not $subscriptionData.subscription->isActive()}
                                {' (disabled)'}
                            {/if}
                        </span>
                    </h3>
                    <input
                            class="input-group form-control account-subscription-name"
                            style="display: none"
                            type="text"
                            name="subscription_name"
                            value="{if $subscriptionData.subscription->getName()}{$subscriptionData.subscription->getName()}{else}Autoship {$autoshipNumber}{/if}"
                            data-action="updateSubscriptionName"
                            data-trigger="change"
                    >
                    <div class="next-ship form-group">
                        <label
                                for="next-delivery-{$id_subscription}">{l s='Next ship date' mod='productsubscription'}:
                        </label>
                        <input
                                id="next-delivery-{$id_subscription}"
                                class="input-group form-control"
                                type="text"
                                name="next_delivery"
                                value="{$subscriptionData.subscription->getNextDelivery()|date_format:'Y-m-d'}"
                                data-action="setNextDelivery"
                                data-trigger="change"
                                data-plugin="datepicker"
                        >
                    </div>
                    <div class="periodicity form-group">
                        <select
                                class="input-group form-control"
                                name="id_periodicity"
                                data-action="setPeriodicity"
                                data-trigger="change"
                        >
                            {foreach from=$periodicityList item=periodicity}
                                <option value="{$periodicity.id_periodicity}" {if $subscriptionData.subscription->getPeriodicity()->getId() == $periodicity.id_periodicity} selected="selected"{/if}>{$periodicity.name}</option>
                            {/foreach}
                        </select>
                    </div>
                </div>
                <div class="actions" style="font-size: small">
                    <a
                        href="{url entity='module' name='productsubscription' controller='account'}"
                        data-action="displayPaymentForm"
                        data-trigger="click"
                    >
                        Ship now
                    </a>
                    <a
                        href="{url entity='module' name='productsubscription' controller='account'}"
                        data-action="displayQuickOrderForm"
                        data-trigger="click"
                        data-index-subscription="{$autoshipNumber}"
                    >
                        Add products
                    </a>
                    <a
                        id="productsubscription-account-redeem-points-toggle-{$id_subscription}"
                        href="{url entity='module' name='productsubscription' controller='account'}"
                        data-action="{if $subscriptionData.redeemPoints}clearPoints{else}displayPointsForm{/if}"
                        data-trigger="click"
                        data-index-subscription="{$autoshipNumber}"
                    >
                        {if $subscriptionData.redeemPoints}
                            Clear redeem points
                        {else}
                            Apply redeem points
                        {/if}
                    </a>
                    <a
                        id="productsubscription-account-promo-code-toggle-{$id_subscription}"
                        href="{url entity='module' name='productsubscription' controller='account'}"
                        data-action="{if $subscriptionData.cart_rule}clearPromoCode{else}displayPromoCodeForm{/if}"
                        data-trigger="click"
                        data-index-subscription="{$autoshipNumber}"
                    >
                        {if $subscriptionData.cart_rule}
                            Clear promo code
                        {else}
                            Apply promo code
                        {/if}
                    </a>
                    <a
                        href="{url entity='module' name='productsubscription' controller='account'}"
                        data-action="displayEditCardForm"
                        data-trigger="click"
                    >
                        Change credit card
                    </a>
                    <a
                        id="productsubscription-account-pause-subscription-toggle-{$id_subscription}"
                        href="{url entity='module' name='productsubscription' controller='account'}"
                        data-action="{if $subscriptionData.pausedByCustomer}unpauseSubscription{else}pauseSubscription{/if}"
                        data-trigger="click"
                    >
                        {if $subscriptionData.pausedByCustomer}
                            Unpause autoship
                        {else}
                            Pause autoship
                        {/if}
                    </a>
                    <a
                        href="{url entity='module' name='productsubscription' controller='account'}"
                        data-action="displayCancelSubscriptionForm"
                        data-trigger="click"
                    >
                        {l s='Cancel Autoship' mod='productsubscription'}
                    </a>
                </div>
            {/block}
            {block name="subscription_body"}
                <div class="account-subscription-body">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>{l s='PRODUCT' mod='productsubscription'}</th>
                            <th></th>
                            <th></th>
                            <th>{l s='QTY' mod='productsubscription'}</th>
                            <th>{l s='ACTIONS' mod='productsubscription'}</th>
                        </tr>
                        </thead>
                        <tbody>
                        {foreach from=$subscriptionData.products item=product}
                            {assign var=subscriptionProduct value=$subscriptionData.subscription->getSubscriptionProduct($product.id_product|intval, $product.id_product_attribute|intval)}
                            <tr id="productsubscription-account-product-{$id_subscription}-{$product.id_product}-{$product.id_product_attribute}">
                                <td><a href="{url entity='product' id=$product.id_product}">{$product.name}</a></td>
                                <td>{if isset($product.attributes_small)}{$product.attributes_small}{/if}</td>
                                <td>{Tools::displayPrice($product.price)}</td>
                                <td>
                                    <input
                                            class="input-group form-control"
                                            type="number"
                                            name="quantity"
                                            value="{$product.cart_quantity}"
                                            data-action="updateProductQuantity"
                                            data-id-product="{$product.id_product}"
                                            data-id-product-attribute="{$product.id_product_attribute}"
                                            data-trigger="change"
                                    >
                                </td>
                                <td>
                                    <select
                                            name="edit_product"
                                            class="form-control form-control-select"
                                            data-action="editProduct"
                                            data-trigger="change"
                                            data-id-product="{$product.id_product}"
                                            data-id-product-attribute="{$product.id_product_attribute}"
                                    >
                                        <option value="setProductScheduled" {if $subscriptionProduct && $subscriptionProduct->isScheduled()} selected="selected"{/if}>{l s='ship on schedule' mod='productsubscription'}</option>
                                        <option value="setProductNextOnly"{if $subscriptionProduct && $subscriptionProduct->isNextShipmentOnly()} selected="selected"{/if}>{l s='only next shipment' mod='productsubscription'}</option>
                                        <option value="setProductNextSkip"{if $subscriptionProduct && $subscriptionProduct->isSkipNextShipmentOnly()} selected="selected"{/if}>{l s='skip next shipment' mod='productsubscription'}</option>
                                        <option value="displayCancelProductForm">{l s='cancel product' mod='productsubscription'}</option>
                                    </select>
                                </td>
                            </tr>
                        {/foreach}
                        </tbody>
                    </table>
                </div>
            {/block}
            {block name="subscription_footer"}
                <div class="account-subscription-footer row">
                    <div class="footer-left col-6">
                        <p>
                            {l s='Ship to' mod='productsubscription'}:
                            <span id="productsubscription-account-shipping-address-{$id_subscription}">
                                {if $subscriptionData.address_delivery->address1} {$subscriptionData.address_delivery->address1} {else} No delivery address {/if}
                            </span>
                            <a
                                    href="{url entity='module' name='productsubscription' controller='account'}"
                                    data-action="displayAddressesForm"
                                    data-trigger="click"
                                    id="productsubscription-account-ship-to-{$id_subscription}"
                                    style="font-size: smaller"
                            >
                                ({l s='edit' mod='productsubscription'})
                            </a>
                        </p>
                        <p>
                            {l s='Message' mod='productsubscription'}:
                            <a
                                    href="{url entity='module' name='productsubscription' controller='account'}"
                                    data-action="displayMessageForm"
                                    data-trigger="click"
                                    id="productsubscription-account-message-{$id_subscription}"
                                    style="font-size: smaller"
                            >
                                {if $subscriptionData.subscription->getCustomerMessage()}
                                    {Tools::substr($subscriptionData.subscription->getCustomerMessage(), 0, 10)}
                                {else}
                                    add your message
                                {/if}
                            </a>
                        </p>
                    </div>
                    <div class="footer-right col-6">
                        <p>
                            <span id="productsubscription-account-carrier-name-{$id_subscription}">{$subscriptionData.carrier->name}</span>
                            <a
                                href="{url entity='module' name='productsubscription' controller='account'}"
                                data-action="displayDeliveryForm"
                                data-trigger="click"
                                style="font-size: smaller"
                            >
                                (edit)
                            </a>
                            :
                            <span id="productsubscription-account-shipping-amount-{$id_subscription}">
                                {if $subscriptionData.shippingAmount}
                                    {$locale->formatPrice($subscriptionData.shippingAmount, $currencyCode)}
                                {else}
                                    {if $subscriptionData.shippingFree}
                                        {l s='Free shipping' mod='productsubscription'}
                                    {else}
                                        {l s='calculated at place order' mod='productsubscription'}
                                    {/if}
                                {/if}
                            </span>
                        </p>
                        <p>{l s='TOTAL (tax excl.)' mod='productsubscription'}
                            :
                            <span id="productsubscription-account-total-amount-{$id_subscription}">{$locale->formatPrice($subscriptionData.totalAmount, $currencyCode)}</span>
                        </p>
                        <p>{l s='TOTAL (tax incl.)' mod='productsubscription'}
                            : calculated at place order
                        </p>
                    </div>
                </div>
            {/block}
        </form>
        <hr>
    </div>
{/block}