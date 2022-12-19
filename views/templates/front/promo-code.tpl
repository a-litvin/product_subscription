<div class="productsubscription-popup-content">
    <form
        id="productsubscription-account-form-modal"
        method="post"
        action="{url entity='module' name='productsubscription' controller='account'}"
        data-action="addPromoCode"
        data-id-subscription="{$id_subscription}"
    >
        <div class="form-group">
            <p>{l s='Apply promo code to subscription' mod='productsubscription'}:</p>
        </div>
        <div class="form-group">
            <input type="text" name="promo_code" placeholder="{l s='Enter the code' mod='productsubscription'}"</input>
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary">Apply</button>
        </div>
    </form>
</div>