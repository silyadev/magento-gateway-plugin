<?php

namespace Vendo\Gateway\Model;

use Magento\Framework\Locale\ResolverInterface;
use Magento\Quote\Model\Quote;
use Vendo\Gateway\Gateway\Pix;

class BasicService
{
    /**
     * @var ResolverInterface
     */
    private $localeResolver;

    /**
     * @var Pix
     */
    private $paymentConfig;

    public function __construct(ResolverInterface $localeResolver, Pix $paymentConfig)
    {
        $this->localeResolver = $localeResolver;
        $this->paymentConfig = $paymentConfig;
    }
    public function getBaseVerificationUrlRequestData(Quote $quote): array
    {
        $quoteItems = $quote->getItems();
        $items = [];

        foreach ($quoteItems as $quoteItem) {
            if ($quoteItem->getParentItem()) {
                continue;
            }
            $items[] = [
                'item_id' => $quoteItem->getSku(),
                'item_description' => $quoteItem->getName(),
                'item_price' => $quoteItem->getPrice(),
                'item_quantity' => $quoteItem->getQty()
            ];
        }

        $billingAddress = $quote->getBillingAddress();
        $shippingAddress = $quote->getShippingAddress();
        $storeId = $quote->getStoreId();

        $params = [
            'external_references' => [
                'transaction_reference' => $quote->getReservedOrderId()
            ],
            'items' => $items,
//            'payment_details' => ['payment_method' => 'pix'],
            'customer_details' => [
                'first_name' => $billingAddress->getFirstname(),
                'last_name' => $billingAddress->getLastname(),
                'language' => strstr($this->localeResolver->getLocale(), '_', true),
                'address' => implode(' ', $billingAddress->getStreet()),
                'country' => $billingAddress->getCountryId(),
                'postal_code' => $billingAddress->getPostcode(),
                'email' => $billingAddress->getEmail(),
                'phone' => $billingAddress->getTelephone(),
//                'national_identifier' => $quote->getPayment()->getAdditionalInformation('national_identifier')
            ],
            'shipping_address' => [
                'first_name' => $shippingAddress->getFirstname(),
                'last_name' => $shippingAddress->getLastname(),
                'address' => implode(' ', $shippingAddress->getStreet()),
                'country' => $shippingAddress->getCountryId(),
                'postal_code' => $shippingAddress->getPostcode(),
                'phone' => $shippingAddress->getTelephone(),
                'city' => $shippingAddress->getCity(),
                'state' => $shippingAddress->getRegionCode()
            ],
            'request_details' => [
                'ip_address' => $quote->getRemoteIp(),
                'browser_user_agent' => $_SERVER["HTTP_USER_AGENT"]
            ],
            'amount' => $quote->getGrandTotal(),
            'currency' => $quote->getCurrency()->getQuoteCurrencyCode(),
            'merchant_id' => $this->paymentConfig->getMerchantId($storeId),
            'site_id' => $this->paymentConfig->getSiteId($storeId),
            'api_secret' => $this->paymentConfig->getApiSecret($storeId),
            'is_test' => $this->paymentConfig->getIsTestMode($storeId),
            'success_url' => $this->paymentConfig->getSuccessUrl()
        ];

        return $params;
    }
}
