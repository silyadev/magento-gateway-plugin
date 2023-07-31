define(
    [
        'Magento_Checkout/js/view/payment/default',
        'jquery',
        'mage/validation'
    ],
    function (Component, $) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Vendo_Gateway/payment/pix-form',
                nationalIdentifier: ''
            },

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
            }
        });
    }
);
