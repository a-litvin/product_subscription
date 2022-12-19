<div class="productsubscription-popup-content">
    <form
            id="productsubscription-account-form-modal"
            method="post"
            action="{url entity='module' name='productsubscription' controller='account'}"
            data-action="addShippingAddress"
            data-id-subscription="{$id_subscription}"
    >
        <div id="delivery-address">
            {render file                      = 'checkout/_partials/address-form.tpl'
            ui                        = $addressForm
            type                      = "delivery"
            form_has_continue_button  = false
            }
        </div>
    </form>
</div>