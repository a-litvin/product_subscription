{capture name=path}{l s='Order Form' mod='gcorderform'}{/capture}
<!-- MODULE GcOrderForm -->
<style>
    .image img:hover {
        width: {$gcof_big_size.width|intval}px;
        height: {$gcof_big_size.height|intval}px;
    }
</style>
<div class="orderform_content table-responsive">
    <div id="help_them" class="block">
        <div class="block_content">
            <div class="help_step" id="help_step1">{l s='Select category of your choice' mod='gcorderform'}</div>
            <div class="help_step" id="help_step2">{l s='Fill the order form' mod='gcorderform'}</div>
            <div class="help_step" id="help_step3">{l s='Add your product to cart' mod='gcorderform'}</div>
        </div>
    </div>
    <select id="categories">
        <option value="0">{l s='All categories' mod='gcorderform'}</option>
        {foreach from=$gcof_categories item=categorie}
            <option value="{$categorie.id_category|intval}" {if $selectedCategoryId && $selectedCategoryId == $categorie.id_category}selected{/if}>{$categorie.name|escape:'html':'UTF-8'}</option>
        {/foreach}
    </select>
    <br/>
    <table class="orderform_table_{$gcof_psversion|escape:'html':'UTF-8'} table table-hover order-table" cellspacing="0">
        <thead>
        <tr class="head">
            {if $gcof_image == 1}
                <th class="image item">{l s='Image' mod='gcorderform'}</th>
            {/if}
            {*				<th class="ref first_item">{l s='Ref.' mod='gcorderform'}</th>*}
            <th class="name item">{l s='Name' mod='gcorderform'}</th>
            <th class="size item">{l s='Size' mod='gcorderform'}</th>
            {if $gcof_stock == 1}
                <th class="stock">{l s='Stock' mod='gcorderform'}</th>
            {/if}
            <th class="quantity item">{l s='Quantity' mod='gcorderform'}</th>
            <th class="price last_item">{l s='Price' mod='gcorderform'}</th>
        </tr>
        </thead>
        <tbody>
        {foreach from=$gcof_products item=product name=myLoop}
            {if !is_array($product.declinaison) && $product.declinaison|substr:0:15 == "ProdSansDecli##"}
                <tr class="item">
                    <td class="name">
                        <div class="name-bl">
                            {if $gcof_link == 1}
                                <a href="{$product.link|escape:'html':'UTF-8'}">
                                    <span class="title">{$product.name|escape:'html':'UTF-8'}</span>
                                </a>
                            {else}
                                <span class="title">{$product.name|escape:'html':'UTF-8'}</span>
                            {/if}
                            <input type="hidden" value="{$product.id_product|intval}" class="id_product"/>
                            <input type="hidden" value="0" class="id_product_attribute"/>
                        </div>
                    </td>
                    <td class="size">
                        <div class="size-bl">
                            {$decli.libelle|escape:'html':'UTF-8'}
                        </div>
                    </td>
                    <td class="quantity text-center">
                        <div class="product-quantity clearfix">
                            <div class="qty">
                                <input
                                        type="text"
                                        name="qty"
                                        class="quantity_wanted input-group"
                                        value="0"
                                        min="0"
                                        max="99999"
                                >
                            </div>
                        </div>
                        <span class="min"></span>
                    </td>
                    <td class="price">
                        <div class="price-bl">
                            {$product.price|escape:'html':'UTF-8'}
                        </div>
                    </td>
                </tr>
            {else}
                {foreach from=$product.declinaison item=decli}
                    <tr class="item">
                        <td class="name">
                            <div class="name-bl">
                                {if $gcof_link == 1}
                                    <a href="{$product.link|escape:'html':'UTF-8'}">
                                        <span class="title">{$product.name|escape:'html':'UTF-8'}</span>
                                    </a>
                                {else}
                                    <span class="title">{$product.name|escape:'html':'UTF-8'}</span>
                                {/if}
                                <input type="hidden" value="{$product.id_product|intval}" class="id_product"/>
                                    <input type="hidden" value="{$decli.id_product_attribute|intval}" class="id_product_attribute"/>
                            </div>
                        </td>
                        <td class="size">
                                <div class="size-bl">
                                    {$decli.libelle|escape:'html':'UTF-8'}
                                </div>
                        </td>
                        <td class="quantity text-center">
                                {if $decli.minimal_quantity|intval > 0}
                                    <input
                                            type="text"
                                            name="qty"
                                            class="quantity_wanted input-group"
                                            value="0"
                                            min="{$decli.minimal_quantity|intval}"
                                            max="99999"
                                    >
                                {else}
                                    <input
                                            type="text"
                                            name="qty"
                                            class="quantity_wanted input-group"
                                            value="0"
                                            min="0"
                                            max="99999"
                                    >
                                {/if}
                        </td>
                        <td class="price">
                                <div class="price-bl">
                                    {$decli.price|escape:'html':'UTF-8'}
                                </div>
                        </td>
                    </tr>
                {/foreach}
            {/if}
        {/foreach}
        </tbody>
        <tfoot>
        <tr class="last">
            <td colspan="4" class="text-center">
                <button id="add_to_cart_fix" class="btn btn-primary" data-button-action="add-to-cart">
                    <i class="material-icons">&#xE547;</i>
                    {l s='Add to subscription' mod='productsubscription'}
                </button>
            </td>
        </tr>
        </tfoot>
    </table>
    <img id="bigpic" class="hack156"/>
</div>
<!-- /MODULE GcOrderForm -->

{*
 * GcOrderForm
 *
 * @author    Grégory Chartier <hello@gregorychartier.fr>
 * @copyright 2018 Grégory Chartier (https://www.gregorychartier.fr)
 * @license   Commercial license see license.txt
 * @category  Prestashop
 * @category  Module
 *
*}

<script type="text/javascript">
    // prestashop.blockcart.showModal = 0;

    var gcof_empty = {$gcof_empty|intval};
    var gcof_stock = {$gcof_stock|intval};
    var gcof_image = {$gcof_image|intval};
    var gcof_link = {$gcof_link|intval};
    {if $gcof_image}
    var gcof_image_size_width = {$gcof_image_size.width|intval};
    var gcof_image_size_height = {$gcof_image_size.height|intval};
    {/if}
    var controller_url = "{$controller_url|escape:'html':'UTF-8'}";
    var gcof_quantitybuttons = {$gcof_quantity_buttons|intval};
    var subscriptionId = {$id_subscription|intval};
    var subscriptionIndex = {$subscriptionIndex|intval};

    // <![CDATA[
    {literal}
    $(document).ready(function () {
        $('#categories').change(function () {
            $.ajax({
                url: controller_url,
                type: "Post",
                dataType: 'json',
                data: {
                    idCategory: $('#categories').val(),
                    action: "getProductsByCategory",
                    ajax: 1,
                    idSubscription: subscriptionId
                },
                success: function (data) {
                    var cell_image, cell_reference, cell_name, cell_decli, cell_stock, cell_quantity, cell_price, row;
                    $('.orderform_content table tbody tr').remove();
                    $.each(data, function (index, element) {
                        index = Number(index);

                        if (Number.isInteger(index)) {
                            if ((!$.isArray(element.declinaison)) && (element.declinaison.substr(0, 15) == "ProdSansDecli##")) {
                                if (gcof_image == 1)
                                    cell_image = '<td class="image"><img src="' + element.big + '" alt="' + element.name + '" width="' + gcof_image_size_width + '" height="' + gcof_image_size_height + '" /></td>';

                                cell_reference = '<td class="ref"><p class="ref">' + element.reference + '</p></td>';
                                if (gcof_link == 1)
                                    cell_name = '<td class="name"><a href="' + element.link + '"><span class="title">' + element.name + '</span></a>';
                                else
                                    cell_name = '<td class="name"><span class="title">' + element.name + '</span>';
                                cell_name += '<input type="hidden" value="' + element.id_product + '" class="id_product"/><input type="hidden" value="0" class="id_product_attribute"/></td>';
                                if (gcof_stock == 1)
                                    cell_stock = '<td class="stock">' + element.quantityavailable + '</td>';
                                if (element.minimal_quantity > 0)
                                    cell_quantity = '<td class="quantity text-center"><div class="product-quantity clearfix"><div class="qty"><input type="text" name="qty" class="quantity_wanted input-group" value="0" min="' + element.minimal_quantity + '" max="99999"></div></div>';
                                else
                                    cell_quantity = '<td class="quantity text-center"><div class="product-quantity clearfix"><div class="qty"><input type="text" name="qty" class="quantity_wanted input-group" value="0" min="0" max="99999"></div></div>';

                                cell_quantity += '<span class="min">' + element.minimal_quantity + '</span></td>';
                                cell_price = '<td class="price">' + element.price + '</td>';

                                row = '<tr class="item">';

                                if (gcof_image == 1)
                                    row = row + cell_image;

                                row = row + cell_reference + cell_name;
                                if (gcof_stock == 1)
                                    row = row + cell_stock;

                                row = row + cell_quantity + cell_price + '</tr>';

                                var newRow = $(row).clone();
                                $('.orderform_content table tbody').append(newRow);
                            } else {
                                $.each(element.declinaison, function (index2, elementdecli) {
                                    if (gcof_image == 1)
                                        cell_image = '<td class="image"><img src="' + elementdecli.big + '" alt="' + element.name + '" width="' + gcof_image_size_width + '" height="' + gcof_image_size_height + '" /></td>';

                                    cell_reference = '<td class="ref"><p class="ref">' + elementdecli.reference + '</p></td>';
                                    if (gcof_link == 1)
                                        cell_name = '<td class="name"><a href="' + elementdecli.link + '"><span class="title">' + element.name + '</span> <span class="decli-name">' + elementdecli.libelle + '</span></a>';
                                    else
                                        cell_name = '<td class="name"><span class="title">' + element.name + '</span> <span class="decli-name">' + elementdecli.libelle + '</span>';
                                    cell_name += '<input type="hidden" value="' + element.id_product + '" class="id_product"/><input type="hidden" value="' + elementdecli.id_product_attribute + '" class="id_product_attribute"/></td>';
                                    if (gcof_stock == 1)
                                        cell_stock = '<td class="stock">' + elementdecli.quantityavailable + '</td>';
                                    if (elementdecli.minimal_quantity > 0)
                                        cell_quantity = '<td class="quantity text-center"><div class="product-quantity clearfix"><div class="qty"><input type="text" name="qty" class="quantity_wanted input-group" value="0" min="' + elementdecli.minimal_quantity + '" max="99999"></div></div>';
                                    else
                                        cell_quantity = '<td class="quantity text-center"><div class="product-quantity clearfix"><div class="qty"><input type="text" name="qty" class="quantity_wanted input-group" value="0" min="0" max="99999"></div></div>';

                                    cell_quantity += '<span class="min">' + elementdecli.minimal_quantity + '</span></td>';
                                    cell_price = '<td class="price">' + elementdecli.price + '</td>';

                                    row = '<tr class="item">';

                                    if (gcof_image == 1)
                                        row = row + cell_image;

                                    row = row + cell_reference + cell_name;
                                    if (gcof_stock == 1)
                                        row = row + cell_stock;

                                    row = row + cell_quantity + cell_price + '</tr>';

                                    var newRow = $(row).clone();
                                    $('.orderform_content table tbody').prepend(newRow);
                                });
                            }
                        }
                    });
                    if (gcof_quantitybuttons == 1)
                        createProductSpin();
                }
            });
        });

        $('#add_to_cart_fix').unbind('click').click(function () {
            var products = [];

            var $quantity_wanted = $('.orderform_content tbody tr .quantity_wanted').filter(function () {
                return parseInt(this.value, 10) !== 0;
            });

            if ($quantity_wanted.length > 0) {
                $($quantity_wanted).each(function () {
                    var $currentRow = $(this).parents('tr.item');

                    var dataItem = {
                        id_product: parseInt($currentRow.find('.name .id_product').val()),
                        id_product_attribute: parseInt($currentRow.find('.name .id_product_attribute').eq(index).val()),
                        id_customization: 0,
                        quantity: parseInt($(this).val())
                    };

                    products.push(dataItem);

                    if (gcof_empty == 1) {
                        $(this).val("0");
                    }
                });
            }

            if (products.length > 0) {
                $.ajax({
                    url: controller_url,
                    type: 'POST',
                    data: {
                        action: "addProductsToSubscription",
                        products: JSON.stringify(products),
                        ajax: 1,
                        idSubscription: subscriptionId,
                        indexSubscription: subscriptionIndex
                    },
                    dataType: 'json',
                    beforeSend: function () {
                        if (undefined === $.fancybox) {
                            return;
                        }

                        $.fancybox.showLoading();
                    },
                    success: function (responseData, textStatus, jqXHR) {
                        if (responseData.hasOwnProperty('success') && true === responseData.success) {
                            delete responseData.success;

                            if ($.isEmptyObject(responseData)) {
                                return;
                            }

                            $.each(responseData, function (elementSelector, value) {
                                if (typeof value == "string") {
                                    $(elementSelector).html(value);
                                }
                            });
                        }

                        if (undefined !== $.fancybox) {
                            $.fancybox.close();
                        }

                        let date = new Date();
                        $('.account-productsubscriptions-wrapper').find('[data-plugin="datepicker"]').datepicker({
                            minDate: new Date(),
                            dateFormat: 'yy-mm-dd',
                        });
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        //
                    },
                    complete: function (jqXHR, textStatus) {
                        if (undefined !== $.fancybox) {
                            $.fancybox.hideLoading();
                        }
                    }
                });
            }

            return false;
        });
        if (gcof_quantitybuttons == 1)
            createProductSpin();
    });

    function createProductSpin() {
        $('.orderform_content tr').each(function () {
            var currentRow = $(this);
            quantityInput = currentRow.find('.quantity_wanted');
            quantityInput.TouchSpin({
                verticalbuttons: true,
                verticalupclass: 'material-icons touchspin-up',
                verticaldownclass: 'material-icons touchspin-down',
                buttondown_class: 'btn btn-touchspin js-touchspin',
                buttonup_class: 'btn btn-touchspin js-touchspin',
                min: 0,
                max: 1000000
            });
        });
    }
    {/literal}
    // ]]>
</script>