<?php

namespace Vendo\Gateway\Request\Pix;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Framework\Locale\ResolverInterface;
use Vendo\Gateway\Gateway\Pix;

class AuthorizeRequestBuilder implements BuilderInterface
{
    private $localeResolver;

    private $paymentConfig;

    public function __construct(ResolverInterface $localeResolver, Pix $paymentConfig)
    {
        $this->localeResolver = $localeResolver;
        $this->paymentConfig = $paymentConfig;
    }

    /**
     * @inheritDoc
     */
    public function build(array $buildSubject)
    {
        /** @var PaymentDataObjectInterface $payment */
        $payment = $buildSubject['payment'];
        $order = $payment->getOrder();

        /** @var OrderItemInterface[] $orderItems */
        $orderItems = $order->getItems();
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
        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();
        $storeId = $order->getStoreId();

        return [
            'external_references' => [
                'transaction_reference' => $order->getOrderIncrementId()
            ],
            'items' => $items,
            'payment_details' => ['payment_method' => 'pix'],
            'customer_details' => [
                'first_name' => $billingAddress->getFirstname(),
                'last_name' => $billingAddress->getLastname(),
                'language' => strstr($this->localeResolver->getLocale(), '_', true),
                'address' => $billingAddress->getStreetLine1() . ' ' . $billingAddress->getStreetLine2(),
                'country' => $billingAddress->getCountryId(),
                'postal_code' => $billingAddress->getPostcode(),
                'email' => $billingAddress->getEmail(),
                'phone' => $billingAddress->getTelephone(),
                'national_identifier' => $payment->getPayment()->getAdditionalInformation('national_identifier')
            ],
            'shipping_address' => [
                'first_name' => $shippingAddress->getFirstname(),
                'last_name' => $shippingAddress->getLastname(),
                'address' => $shippingAddress->getStreetLine1() . ' ' . $shippingAddress->getStreetLine2(),
                'country' => $shippingAddress->getCountryId(),
                'postal_code' => $shippingAddress->getPostcode(),
                'phone' => $shippingAddress->getTelephone(),
                'city' => $shippingAddress->getCity(),
                'state' => $shippingAddress->getRegionCode()
            ],
            'request_details' => [
                'ip_address' => $order->getRemoteIp(),
                'browser_user_agent' => $_SERVER["HTTP_USER_AGENT"]
            ],
            'amount' => $order->getGrandTotalAmount(),
            'currency' => $order->getCurrencyCode(),
            'merchant_id' => $this->paymentConfig->getMerchantId($storeId),
            'site_id' => $this->paymentConfig->getSiteId($storeId),
            'api_secret' => $this->paymentConfig->getApiSecret($storeId),
            'is_test' => $this->paymentConfig->getIsTestMode($storeId),
            'success_url' => $this->paymentConfig->getSuccessUrl()
        ];
    }
}
