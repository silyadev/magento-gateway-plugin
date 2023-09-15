<?php

namespace Vendo\Gateway\Model;

use Vendo\Gateway\Api\PixServiceInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\Locale\ResolverInterface;
use Vendo\Gateway\Gateway\Pix;
use Vendo\Gateway\Adapter\Pix as PixAdapter;

class PixService implements \Vendo\Gateway\Api\PixServiceInterface
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    private $localeResolver;

    /**
     * @var \Vendo\Gateway\Gateway\Pix
     */
    private $paymentConfig;

    /**
     * @var \Vendo\Gateway\Adapter\Pix
     */
    private $pixAdapter;

    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        \Vendo\Gateway\Gateway\Pix $paymentConfig,
        \Vendo\Gateway\Adapter\Pix $pixAdapter
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->localeResolver = $localeResolver;
        $this->paymentConfig = $paymentConfig;
        $this->pixAdapter = $pixAdapter;
    }

    /**
     * @return string
     */
    public function getVerificationUrl(): string
    {
        $quote = $this->checkoutSession->getQuote();
        $quote->reserveOrderId();

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
            'payment_details' => ['payment_method' => 'pix'],
            'customer_details' => [
                'first_name' => $billingAddress->getFirstname(),
                'last_name' => $billingAddress->getLastname(),
                'language' => strstr($this->localeResolver->getLocale(), '_', true),
                'address' => implode(' ', $billingAddress->getStreet()),
                'country' => $billingAddress->getCountryId(),
                'postal_code' => $billingAddress->getPostcode(),
                'email' => $billingAddress->getEmail(),
                'phone' => $billingAddress->getTelephone(),
                'national_identifier' => $quote->getPayment()->getAdditionalInformation('national_identifier')
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

        $response = $this->pixAdapter->authorize($params);

        return $response['result']['verification_url'];
    }
}
