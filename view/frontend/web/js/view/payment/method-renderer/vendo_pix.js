define(
    [
        'Magento_Checkout/js/view/payment/default',
        'jquery',
        'Magento_Checkout/js/model/url-builder',
        'Vendo_Gateway/js/action/get-pix-verification-url',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/payment/additional-validators',
        'mage/validation'
    ],
    function (Component, $, urlBuilder, verificationUrl, placeOrderAction, fullScreenLoader, additionalValidators) {
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

                return $(form).validation() && $(form).validation('isValid') && additionalValidators.validate();
            },

            pixPlaceOrder: function()
            {
                var self = this;

                if (self.validate())
                {
                    fullScreenLoader.startLoader();
                    verificationUrl().then(function(response) {
                        self.isPlaceOrderActionAllowed(true);
                        self.getPlaceOrderDeferredObject()
                            .done(
                                function () {
                                    window.location.href = response;
                                }
                            ).always(
                            function () {
                                self.isPlaceOrderActionAllowed(true);
                            }
                        );

                    });
                }

                return false;
            }
        });
    }
);
