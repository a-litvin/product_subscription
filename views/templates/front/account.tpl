{extends file='customer/page.tpl'}

{block name='page_header_container'}
    <header class="page-header">
        <h1 class="h1 page-title"><span>{l s='Product Subscription' mod='productsubscription'}</span></h1>
    </header>
{/block}

{block name='page_title'}
    {if !empty($subscriptionsData)}
        {l s='Your subscriptions' mod='productsubscription'}:
    {else}
        {l s='No subscriptions found' mod='productsubscription'}.
    {/if}
    <hr>
{/block}

{block name='page_content'}
    <div class="account-productsubscriptions-wrapper">
        {if !empty($subscriptionsData)}
            {foreach from=$subscriptionsData key=index item=subscriptionData}
                {include
                file="module:productsubscription/views/templates/front/_partials/subscription.tpl"
                index=$index subscription=$subscription periodicityList=$periodicityList locale=$locale currencyCode=$currencyCode
                }
            {/foreach}
        {/if}
    </div>
{/block}