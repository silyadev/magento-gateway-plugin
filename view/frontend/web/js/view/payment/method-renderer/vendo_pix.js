define(
    [
        'Magento_Checkout/js/view/payment/default',
        'jquery',
        'Magento_Checkout/js/model/url-builder',
        'Vendo_Gateway/js/action/get-verification-url',
        'mage/validation'
    ],
    function (Component, $, urlBuilder, verificationUrl) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Vendo_Gateway/payment/pix-form',
                nationalIdentifier: ''
            },
            redirectAfterPlaceOrder: true,

            initObservable: function () {
                this._super()
                    .observe('nationalIdentifier');
                return this;
            },

            getCode: function() {
                return 'vendo_pix';
            },

            getData: function() {
                return {
                    'method': this.item.method,

                    'additional_data': {
                        'national_identifier': this.nationalIdentifier(),
                    }
                };
            },

            /**
             * @return {jQuery}
             */
            validate: function () {
                var form = 'form[data-role=vendo_pix-form]';

                return $(form).validation() && $(form).validation('isValid');
            },

            pixPlaceOrder: function()
            {
                var self = this;

                if (self.validate())
                {
                    verificationUrl().then(function(response) {
                        window.location.href = response;
                    }, self.placeOrder.bind(self));
                }

                return false;
            },
        });
    }
);
