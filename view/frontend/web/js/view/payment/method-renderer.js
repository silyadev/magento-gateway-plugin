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
            }
        );
        return Component.extend({});
    }
);
