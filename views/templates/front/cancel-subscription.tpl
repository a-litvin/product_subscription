<div class="productsubscription-popup-content">
    <form
        id="productsubscription-account-form-modal"
        method="post"
        action="{url entity='module' name='productsubscription' controller='account'}"
        data-action="cancelSubscription"
        data-id-subscription="{$id_subscription}"
    >
        <div class="form-group">
            <p>{l s='You are going to delete subscription. Are you sure?' mod='productsubscription'}</p>
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-warning">Delete</button>
        </div>
    </form>
</div>