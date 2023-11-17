<?php

namespace Vendo\Gateway\Model;

use Magento\Checkout\Model\Session;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory;
use Magento\Payment\Gateway\Config\Config;

class BasicService
{
    /**
     * @var ResolverInterface
     */
    private $localeResolver;

    /**
     * @var Config
     */
    private $paymentConfig;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var PaymentHelper
     */
    private $paymentHelper;

    /**
     * @var OrderInterface
     */
    private $order = null;

    /**
     * @var OrderPaymentExtensionInterfaceFactory
     */
    private $paymentExtensionFactory;

    public function __construct(
        ResolverInterface $localeResolver,
        Config $paymentConfig,
        Session $checkoutSession,
        PaymentHelper $paymentHelper,
        OrderPaymentExtensionInterfaceFactory $paymentExtensionFactory
    ) {
        $this->localeResolver = $localeResolver;
        $this->paymentConfig = $paymentConfig;
        $this->checkoutSession = $checkoutSession;
        $this->paymentHelper = $paymentHelper;
        $this->paymentExtensionFactory = $paymentExtensionFactory;
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

        $orderIncrementId = $quote->getReservedOrderId();
        $this->order = $order = $this->paymentHelper->loadOrderByIncrementId($orderIncrementId);
        $this->checkoutSession->setData(PaymentMethod::SESSION_ORDER_KEY, $order->getEntityId());
        $this->checkoutSession->setLastQuoteId($quote->getId());
        $this->checkoutSession->setLastSuccessQuoteId($quote->getId());
        $this->checkoutSession->setLastOrderId($order->getId());
        $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
        $this->checkoutSession->setLastOrderStatus($order->getStatus());
        $quote->setReservedOrderId($orderIncrementId);

        $params = [
            'external_references' => [
                'transaction_reference' => $orderIncrementId
            ],
            'items' => $items,
            'customer_details' => [
                'first_name' => $billingAddress->getFirstname(),
                'last_name' => $billingAddress->getLastname(),
                'language' => strstr($this->localeResolver->getLocale(), '_', true),
                'address' => implode(' ', $billingAddress->getStreet()),
                'country' => $billingAddress->getCountryId(),
                'postal_code' => $billingAddress->getPostcode(),
                'email' => $billingAddress->getEmail(),
                'phone' => $billingAddress->getTelephone(),
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
