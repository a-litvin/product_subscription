const SubscriptionsUpdater = {
    subscriptionsSelector: '.account-productsubscriptions-wrapper',
    formModalSelector: '#productsubscription-account-form-modal',
    formModalLinkSelector: '#productsubscription-account-form-modal > a',
    accountSubscriptionHeaderSector: '.account-subscription-header',
    subscriptionNameSelector: 'h3.account-subscription-name',
    subscriptionNameInputSelector: 'input.account-subscription-name',
    requestData: undefined,
    requestUrl: undefined,
    customEvent: undefined,
    refresh: false,
    init() {
        this.initPlugins();
        this.setListeners();
    },
    reset() {
        this.requestData = undefined;
        this.requestUrl = undefined;
        this.customEvent = new Event('productsubscription_modal_open', {bubbles: true});
        this.refresh = false;
    },
    setListeners() {
        $('body').on('change', this.subscriptionsSelector + ' [data-trigger="change"]', this.sendRequest.bind(this));
        $('body').on('click', this.subscriptionsSelector + ' [data-trigger="click"]', this.sendRequest.bind(this));
        $('body').on('submit', this.formModalSelector, this.sendRequest.bind(this));
        $('body').on('click', this.formModalLinkSelector, this.sendRequest.bind(this));
        $('body').on('click', this.subscriptionNameSelector, this.showSubscriptionNameInput.bind(this));
        $('body').on('click', this.hideSubscriptionNameInput.bind(this));
    },
    showSubscriptionNameInput(e) {
        e.stopPropagation();
        let subscriptionName = e.target;
        let parentDiv = $(subscriptionName).closest(this.accountSubscriptionHeaderSector);
        let subscriptionNameInput = parentDiv.children(this.subscriptionNameInputSelector);
        $(subscriptionName).hide();
        $(subscriptionNameInput).show();
    },
    hideSubscriptionNameInput(e) {
        let clickedElement = e.target;

        if(!$(clickedElement).closest(this.subscriptionNameInputSelector).length
            && $(this.subscriptionNameInputSelector + ':visible')) {
            $(this.subscriptionNameInputSelector).hide();
            $('body').find(this.subscriptionNameSelector).show();
        }
    },
    initPlugins() {
        let date = new Date();
        $(this.subscriptionsSelector).find('[data-plugin="datepicker"]').datepicker({
            minDate: new Date(),
            dateFormat: 'yy-mm-dd',
        });
    },
    showModal(message) {
        $.fancybox.open({
                content: message,
                afterClose: SubscriptionsUpdater.closeModal
            },
            {
                padding: 20,
            });
    },
    closeModal() {
        let html = this.content;
        let element = $($.parseHTML(html)).filter('#productsubscription-popup-payment-content');

        if (0 === element.length) {
            if (true === SubscriptionsUpdater.refresh) {
                document.location.reload();
            }

            return;
        }

        let dataset = $(element).data();

        SubscriptionsUpdater.requestUrl = dataset.url;
        delete dataset.url;
        SubscriptionsUpdater.requestData = dataset;
        SubscriptionsUpdater.requestData.ajax = 1;

        SubscriptionsUpdater.makeAjaxCall();
    },
    dispatchEvent(modalElement, event) {
        $(modalElement).get(0).dispatchEvent(event);
    },
    handleResponse(data) {
        delete data.success;

        if ($.isEmptyObject(data)) {
            return;
        }

        $.each(data, function (elementSelector, value) {
            if (typeof value == "string") {
                $(elementSelector).text(value);
            } else {
                if (value.hasOwnProperty('data-action')) {
                    $(elementSelector).attr('data-action', value["data-action"]);
                }

                if (value.hasOwnProperty('text')) {
                    $(elementSelector).text(value["text"]);
                }
            }
        });
    },
    sendRequest(e) {
        e.preventDefault();
        e.stopPropagation();

        let selectedElement = e.target;
        this.prepareRequestData(selectedElement);
        this.makeAjaxCall();
    },
    makeAjaxCall() {
        $.ajax({
            url: this.requestUrl,
            type: 'POST',
            data: this.requestData,
            modalElement: this.subscriptionsSelector,
            event: this.customEvent,
            dataType: 'json',
            showModalAction: this.showModal,
            handleResponseAction: this.handleResponse,
            dispatchEventAction: this.dispatchEvent,
            beforeSend: function () {
                if (undefined === $.fancybox) {
                    return;
                }

                $.fancybox.showLoading();
            },
            success: function (responseData, textStatus, jqXHR) {
                if (undefined === $.fancybox) {
                    return;
                }

                if (false === responseData.success) {
                    this.showModalAction(responseData.message);

                    if (responseData.hasOwnProperty('refresh')) {
                        SubscriptionsUpdater.refresh = true;
                    }
                }

                if (responseData.hasOwnProperty('form')) {
                    this.showModalAction(responseData.form)

                    if (responseData.hasOwnProperty('braintree')) {
                        this.event.braintree = true;
                    }

                    this.dispatchEventAction(this.modalElement, this.event);
                } else {
                    this.handleResponseAction(responseData);
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
    prepareRequestData(selectedElement) {
        this.reset();
        this.requestData = Object.assign({}, selectedElement.dataset);
        this.requestData.ajax = 1;

        if ('form' === $(selectedElement).prop("tagName").toLowerCase()) {
            this.requestUrl = $(selectedElement).attr('action');
            this.requestData.formData = JSON.stringify($(selectedElement).serializeArray());

            if (undefined !== $.fancybox) {
                $.fancybox.close();
            }
        } else {
            let form = $(selectedElement).closest('form');
            this.requestUrl = form.attr('action');
            this.requestData.value = selectedElement.value;
            this.requestData.idSubscription = form.data('idSubscription');
        }

        delete this.requestData.trigger;
        delete this.requestData.datepicker;
        delete this.requestData.plugin;
    }
}

jQuery(document).ready(function () {
    SubscriptionsUpdater.init();
});