<div
    id="productsubscription-popup-payment-content"
    class="productsubscription-popup-content"
    data-url="{url entity='module' name='productsubscription' controller='account'}"
    data-action="cancelPaymentForm"
    data-id-subscription="{$id_subscription}"
>
    <div class="row">
        <div class="col-xs-12 col-md-10">
            <div class="bt braintree-row-payment bt__pb-3">
                <div class="bt__mb-2">
                    <i class="material-icons mi-lock">lock</i>
                    <b>{l s='Pay securely using your credit card.' mod='braintreeofficial'}</b>
                    <img style="width: 120px" class="bt__ml-2" src="{$baseDir|addslashes}modules/braintreeofficial/views/img/braintree-paypal.png">
                </div>
                <div class="payment_module braintree-card">
                    {if !isset($init_error)}
                        <form {block form_id}{/block} action="{block form_action}{$braintreeSubmitUrl}{/block}" {block form_data_attributes}data-braintree-card-form{/block} method="post">
                            {if isset($active_vaulting) && isset($payment_methods) && !empty($payment_methods)}
                                <div id="bt-vault-form" class="bt__mt-2 bt__mb-3">
                                    <p><b>{l s='Choose your card' mod='braintreeofficial'}:</b></p>
                                    <select name="bt_vaulting_token" data-bt-vaulting-token="bt" class="form-control bt__form-control">
                                        {block use_a_new_card}<option value="">{l s='Use a new card' mod='braintreeofficial'}</option>{/block}
                                        {foreach from=$payment_methods key=method_key  item=method}
                                            {assign var='token' value=$method.token|escape:'htmlall':'UTF-8'}
                                            <option value="{$token}" data-nonce="{$method.nonce}" {if isset($subscription_token) && $token == $subscription_token} selected {/if}>
                                                {if $method.name}{$method.name|escape:'htmlall':'UTF-8'} - {/if}
                                                {$method.info|escape:'htmlall':'UTF-8'}
                                            </option>
                                        {/foreach}
                                    </select>
                                </div>
                            {/if}

                            {block data_form_new_card}
                            <div data-form-new-card {if isset($subscription_token) && $subscription_token} style="display: none" {/if}>
                                <div id="block-card-number" class="form-group">
                                    <label for="card-number" class="bt__form-label">{l s='Card number' mod='braintreeofficial'}</label>
                                    <div id="card-number" class="form-control bt__form-control bt__position-relative" data-bt-field="number">
                                        <div id="card-image"></div>
                                    </div>
                                    <div data-bt-error-msg class="bt__text-danger bt__mt-1"></div>
                                </div>
                                <div class="bt__form-row">
                                    <div id="block-expiration-date" class="form-group col-md-6 bt__flex bt__flex-column">
                                        <label for="expiration-date" class="bt__form-label bt__flex bt__align-items-center bt__flex-grow-1">{l s='Expiration Date' mod='braintreeofficial'}
                                            <span class="text-muted">{l s='(MM/YY)' mod='braintreeofficial'}</span>
                                        </label>
                                        <div id="expiration-date" class="form-control bt__form-control bt__position-relative" data-bt-field="expirationDate"></div>
                                        <div data-bt-error-msg class="bt__text-danger bt__mt-1"></div>
                                    </div>

                                    <div id="block-cvv" class="form-group col-md-6 bt__flex bt__flex-column" data-bt-card-cvv>
                                        <label for="cvv" class="bt__form-label bt__flex bt__align-items-center bt__flex-grow-1">
                                            <div class="bt__flex bt__align-items-center">
                                                <div>
                                                    {l s='CVV' mod='braintreeofficial'}
                                                </div>
                                                <div class="bt__ml-2 bt__flex-grow-1">
                                                    {include file='module:braintreeofficial/views/templates/front/_partials/svg/cvv.tpl'}
                                                </div>
                                            </div>
                                        </label>
                                        <div id="cvv" class="form-control bt__form-control bt__position-relative" data-bt-field="cvv"></div>
                                        <div data-bt-error-msg class="bt__text-danger bt__mt-1"></div>
                                    </div>
                                </div>


                                <input type="hidden" name="deviceData" id="deviceData"/>
                                <input type="hidden" name="client_token" value="{$braintreeToken|escape:'htmlall':'UTF-8'}">
                                <input type="hidden" name="liabilityShifted" id="liabilityShifted"/>
                                <input type="hidden" name="liabilityShiftPossible" id="liabilityShiftPossible"/>
                                <input type="hidden" name="payment_method_nonce" data-payment-method-nonce="bt" />
                                <input type="hidden" name="card_type" data-bt-card-type />
                                <input type="hidden" name="payment_method_bt" value="{$method_bt|escape:'htmlall':'UTF-8'}"/>


                                <div class="clearfix"></div>
                                {if isset($active_vaulting) && $active_vaulting}
                                    <div class="bt__my-2">
                                        <input type="checkbox" name="save_card_in_vault" id="save_card_in_vault"/>
                                        <label for="save_card_in_vault" class="form-check-label bt__form-check-label"> {l s='Memorize my card' mod='braintreeofficial'}</label>
                                    </div>
                                {/if}
                            </div>

                            <div data-form-cvv-field class="bt__hidden">
                                <div id="block-cvv-field" class="form-group col-md-6 bt__pl-0">
                                    <label for="btCvvField" class="bt__form-label">{l s='CVV' mod='braintreeofficial'}</label>
                                    <input type="number" name="btCvvField" id="btCvvField" class="form-control bt__form-control bt__number-field" placeholder="123">
                                </div>
                                <div data-bt-cvv-error-msg class="bt__text-danger bt__mt-1 col-lg-12"></div>
                            </div>
                            {/block}
                        </form>
                        {block data_bt_card_error_msg}
                        <div data-bt-card-error-msg class="alert alert-danger bt__hidden"></div>
                        {/block}
                    {else}
                        <div class="alert alert-danger">{$init_error|escape:'htmlall':'UTF-8'}</div>
                    {/if}
                </div>
            </div>
        </div>
    </div>
    {block name='payment_bt_button'}
    <button type="submit" class="btn btn-primary btn-block btn-lg" onclick="BraintreeSubmitPayment()">
        Submit order
    </button>
    {/block}
</div>