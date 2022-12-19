{if $periodicities|count}
    {if $selectedPeriodicityId}
        {foreach from=$periodicities item=periodicity}
            {if $periodicity.id_periodicity == $selectedPeriodicityId}{$periodicity.name}{/if}
        {/foreach}
    {else}
        {l s='Deliver once' d='Modules.BelVGProductSubscription'}
    {/if}
{/if}