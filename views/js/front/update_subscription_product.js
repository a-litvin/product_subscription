const SubscriptionProductUpdater = {
    periodicitySelector: '.productsubscription_periodicity_dropdown input',
    requestData: undefined,
    requestUrl: undefined,
    subscription: undefined,
    init: function () {
        this.setListeners();
        prestashop.on('updatedCart', this.afterCartUpdate.bind(this));
    },
    setListeners() {
        $('body').on('change', $(this.periodicitySelector), this.sendRequest.bind(this));
    },
    sendRequest(e) {
        e.preventDefault();
        e.stopPropagation();

        let selectTag = e.target;
        this.requestUrl = $(selectTag).data('url');
        this.prepareRequestData(selectTag);

        $.ajax({
            url: this.requestUrl,
            type: 'POST',
            data: this.requestData,
            dataType: 'json',
            context: this,
            beforeSend: function () {
                if (undefined !== $.fancybox) {
                    $.fancybox.showLoading();
                }
            },
            success: function (responseData, textStatus, jqXHR) {
                let data = {};
                data[responseData.property] = responseData.value;
                prestashop.emit(responseData.action, data);

                if (undefined !== responseData.subscription) {
                    if (true === responseData.subscription) {
                        this.subscription = true;
                    } else {
                        this.subscription = false;
                    }
                }
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
    },
    prepareRequestData(selectTag) {
        this.requestData = $(selectTag).data();
        this.requestData.periodicity_id = parseInt(selectTag.value, 10);
        this.requestData.ajax = 1;
        delete this.requestData.url;
    },
    afterCartUpdate() {
        if (undefined !== this.subscription) {
            let payPalContainer = $('div[data-container-express-checkout]');

            if (0 < payPalContainer.length) {
                if (true === this.subscription) {
                    payPalContainer.hide();
                } else {
                    payPalContainer.show();
                }
            }
        }
    }
}

jQuery(document).ready(function() {
    SubscriptionProductUpdater.init();
});