<?php

namespace Vendo\Gateway\Gateway\Request;

use Magento\Framework\Locale\ResolverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Vendo\Gateway\Gateway\Config;


class RequestBuilder
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
     * @var OrderInterface
     */
    private $order = null;

    public function __construct(
        ResolverInterface $localeResolver,
        Config $paymentConfig
    ) {
        $this->localeResolver = $localeResolver;
        $this->paymentConfig = $paymentConfig;
    }

    /**
     * Gather general request
     *
     * @param OrderInterface $order
     * @return array
     */
    public function getBaseVerificationUrlRequestData(OrderInterface $order): array
    {
        $this->order = $order;

        $params = [
            'external_references' => [
                'transaction_reference' => $order->getIncrementId()
            ],
            'items' => $this->getItemsRequestPart(),
            'customer_details' => $this->getCustomerDetailsRequestPart(),
            'shipping_address' => $this->getShippingAddressRequestPart(),
            'request_details' => [
                'ip_address' => $order->getRemoteIp(),
                'browser_user_agent' => $_SERVER["HTTP_USER_AGENT"]
            ],
            'amount' => $order->getGrandTotal(),
            'currency' => $order->getOrderCurrencyCode(),
            'success_url' => $this->paymentConfig->getSuccessUrl()
        ];

        $creds = $this->getApiCredsRequestPart();

        return array_merge($params, $creds);
    }

    public function getCaptureRequestData(OrderPaymentInterface $payment): array
    {
        $this->order = $payment->getOrder();
        $params = [
            'transaction_id' => $payment->getLastTransId()
        ];
        $creds = $this->getApiCredsRequestPart();

        return array_merge($params, $creds);
    }

    public function getRefundRequestData(OrderPaymentInterface $payment, $amount = null): array
    {
        $this->order = $payment->getOrder();

        $params = [
            'transaction_id' => $payment->getLastTransId()
        ];
        if ($amount) {
            $params['partial_amount'] = $amount;
        }
        $creds = $this->getApiCredsRequestPart();

        return array_merge($params, $creds);
    }

    /**
     * Order items request part
     *
     * @return array
     */
    private function getItemsRequestPart(): array
    {
        $orderItems = $this->order->getItems();
        $items = [];

        foreach ($orderItems as $orderItem) {
            if ($orderItem->getParentItem()) {
                continue;
            }
            $items[] = [
                'item_id' => $orderItem->getSku(),
                'item_description' => $orderItem->getName(),
                'item_price' => $orderItem->getPrice(),
                'item_quantity' => $orderItem->getQtyOrdered()
            ];
        }

        return $items;
    }

    /**
     * Customer data request part
     *
     * @return array
     */
    private function getCustomerDetailsRequestPart(): array
    {
        $billingAddress = $this->order->getBillingAddress();

        return [
            'first_name' => $billingAddress->getFirstname(),
            'last_name' => $billingAddress->getLastname(),
            'language' => strstr($this->localeResolver->getLocale(), '_', true),
            'address' => implode(' ', $billingAddress->getStreet()),
            'country' => $billingAddress->getCountryId(),
            'postal_code' => $billingAddress->getPostcode(),
            'email' => $billingAddress->getEmail(),
            'phone' => $billingAddress->getTelephone(),
        ];
    }

    /**
     * Shipping address request part
     *
     * @return array
     */
    private function getShippingAddressRequestPart(): array
    {
        $shippingAddress = $this->order->getShippingAddress();

        return [
            'first_name' => $shippingAddress->getFirstname(),
            'last_name' => $shippingAddress->getLastname(),
            'address' => implode(' ', $shippingAddress->getStreet()),
            'country' => $shippingAddress->getCountryId(),
            'postal_code' => $shippingAddress->getPostcode(),
            'phone' => $shippingAddress->getTelephone(),
            'city' => $shippingAddress->getCity(),
            'state' => $shippingAddress->getRegionCode(),
        ];
    }

    /**
     * API authorization request part
     *
     * @return array
     */
    private function getApiCredsRequestPart(): array
    {
        $storeId = $this->order->getStoreId();

        return [
            'merchant_id' => $this->paymentConfig->getMerchantId($storeId),
            'site_id' => $this->paymentConfig->getSiteId($storeId),
            'api_secret' => $this->paymentConfig->getApiSecret($storeId),
            'is_test' => $this->paymentConfig->getIsTestMode($storeId),
        ];
    }
}
