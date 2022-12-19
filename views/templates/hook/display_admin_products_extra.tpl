<div id="product_subscription_admin_product_extra" class="row">
    <div class="col-md-2">
        <h4>{l s="Enable for subscription" mod="uniquedescription"}:</h4>
    </div>
    <div class="col-md-10">
{*        <input class="switch-input" type="checkbox" name="{$field_prefix}_active" value="1"{if $active} checked="checked"{/if}>*}
        <input type="checkbox" name="{$checkboxName}" value="1" {if $active} checked="checked"{/if}>
    </div>
</div>
