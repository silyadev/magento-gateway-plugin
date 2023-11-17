define(
    [
        'Magento_Checkout/js/view/payment/default',
        'jquery',
        'Magento_Checkout/js/model/url-builder',
        'Vendo_Gateway/js/action/get-crypto-verification-url',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Customer/js/customer-data',
        'mage/validation'
    ],
    function (Component, $, urlBuilder, verificationUrl, placeOrderAction, fullScreenLoader, additionalValidators, customerData) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Vendo_Gateway/payment/crypto',
            },
            redirectAfterPlaceOrder: true,

            getCode: function() {
                return 'vendo_crypto';
            },

            getData: function() {
                return {
                    'method': this.item.method
                };
            },

            /**
             * @return {jQuery}
             */
            validate: function () {
                return additionalValidators.validate();
            },

            cryptoPlaceOrder: function()
            {
                var self = this;

                if (self.validate())
                {
                    fullScreenLoader.startLoader();
                    verificationUrl().then(function(response) {
                        self.isPlaceOrderActionAllowed(true);
                        customerData.invalidate(['cart']);
                        window.location.href = response;
                    });
                }

                return false;
            }
        });
    }
);
