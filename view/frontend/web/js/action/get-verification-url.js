define(
    [
        'Magento_Checkout/js/model/url-builder',
        'mage/storage'
    ],
    function (urlBuilder, storage) {
        'use strict';

        return function ()
        {
            var serviceUrl = urlBuilder.createUrl('/vendo/payments/verification_url', {});

            return storage.get(serviceUrl);
        };
    }
);
