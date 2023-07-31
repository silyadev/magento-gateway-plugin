define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'vendo_payment',
                component: 'Vendo_Gateway/js/view/payment/method-renderer/vendo_payment'
            },
            {
                type: 'vendo_sepa',
                component: 'Vendo_Gateway/js/view/payment/method-renderer/vendo_sepa'
            },
            {
                type: 'vendo_pix',
                component: 'Vendo_Gateway/js/view/payment/method-renderer/vendo_pix'
            }
        );
        return Component.extend({});
    }
);
