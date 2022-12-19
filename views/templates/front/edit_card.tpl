{extends file="module:productsubscription/views/templates/front/payment_bt.tpl"}

{block form_action}
    {url entity='module' name='productsubscription' controller='account'}
{/block}

{block name='payment_bt_button'}
    <button type="submit" form="productsubscription-account-form-modal" class="btn btn-primary btn-block btn-lg">
        Choose your card for subscription
    </button>
{/block}

{block data_form_new_card}{/block}
{block data_bt_card_error_msg}{/block}
{block use_a_new_card}{/block}

{block form_data_attributes}
    data-id-subscription="{$id_subscription}"
    data-action="updateCreditCard"
{/block}

{block form_id}
    id="productsubscription-account-form-modal"
{/block}