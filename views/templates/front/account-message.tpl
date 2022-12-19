<div class="productsubscription-popup-content">
    <form
        id="productsubscription-account-form-modal"
        method="post"
        action="{url entity='module' name='productsubscription' controller='account'}"
        data-action="updateMessage"
        data-id-subscription="{$id_subscription}"
    >
        <div class="form-group">
            <p>{l s='Please add message to subscription' mod='productsubscription'}:</p>
        </div>
        <div class="form-group">
            <textarea rows="3" class="form-control" name="message"{if empty($message)} placeholder="{l s='Enter your message' mod='productsubscription'}"{/if}>{$message}</textarea>
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary">Sav</button>
        </div>
    </form>
</div>