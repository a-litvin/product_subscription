<div class="productsubscription-popup-content">
    <form
        id="productsubscription-account-form-modal"
        method="post"
        action="{url entity='module' name='productsubscription' controller='account'}"
        data-action="editProduct"
        data-value="cancelProduct"
        data-id-subscription="{$id_subscription}"
        data-id-product="{$id_product}"
        data-id-product-attribute="{$id_product_attribute}"
    >
        <div class="form-group">
            <p>{l s='You are going to delete product in subscription. Are you sure?' mod='productsubscription'}</p>
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-warning">Delete</button>
        </div>
    </form>
</div>