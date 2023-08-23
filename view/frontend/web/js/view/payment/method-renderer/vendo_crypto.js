define(
    [
        'Magento_Payment/js/view/payment/cc-form',
        'jquery',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Payment/js/model/credit-card-validation/credit-card-data',
        'Magento_Payment/js/model/credit-card-validation/validator',
        'mage/cookies',
        'jquery/jquery-storageapi',
        'mage/translate'
    ],
    function (Component, $, placeOrder, fullScreenLoader, additionalValidators, creditCardData, validator, $t) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Vendo_Gateway/payment/sepa',
                iban: '',
                bic_swift: ''
            },
            redirectAfterPlaceOrder: true,
            responseRedirectUrl: null,

            afterPlaceOrder: function () {
                try {
                    var redirectUrl = $.cookie('vendo_verification_url');
                    /*console.log(redirectUrl);*/
                    if (redirectUrl !== 'null' && redirectUrl.length) {
                        this.redirectAfterPlaceOrder = false;
                        this.responseRedirectUrl = redirectUrl;
                        var date = new Date();
                        date.setTime(date.getTime() + (30 * 1000));
                        $.cookie('vendo_verification_url', 'null',{ expires: date });
                        window.location.replace(redirectUrl);
                    }
                } catch (error) {
                    /*console.log(error);*/
                }
            },


            /** @inheritdoc */
            initObservable: function () {
                this._super()
                    .observe([
                        'iban',
                        'bic_swift'
                    ]);

                return this;
            },

            /**
             * Init component
             */
            initialize: function () {
                var self = this;

                this._super();

                this.iban.subscribe(function (value) {
                    creditCardData.iban = value;
                });

                this.bic_swift.subscribe(function (value) {
                    creditCardData.bic_swift = value;
                });
            },



            getCode: function () {
                return 'vendo_sepa';
            },

            isActive: function () {
                return true;
            },

            validate: function () {
                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            },

            getData: function () {
                return {
                    'method': this.item.method,
                    'additional_data': {
                        'sepa_iban': this.iban(),
                        'sepa_bic_swift': this.bic_swift(),

                    }
                };
            },
        });
    }
);
