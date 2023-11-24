<?php

namespace Vendo\Gateway\Model;

use Magento\Checkout\Model\Session;
use Vendo\Gateway\Adapter\Pix;
use Vendo\Gateway\Api\PixServiceInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Vendo\Gateway\Gateway\Request\RequestBuilder;
use Magento\Framework\Serialize\Serializer\Serialize;

class PixService implements PixServiceInterface
{
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var Pix
     */
    private $pixAdapter;

    /**
     * @var RequestBuilder
     */
    private $requestBuilder;

    /**
     * @var PaymentHelper
     */
    private $paymentHelper;

    /**
     * @var Serialize
     */
    private $serializer;

    public function __construct(
        Session $checkoutSession,
        Pix $pixAdapter,
        RequestBuilder $requestBuilder,
        PaymentHelper $paymentHelper,
        Serialize $serializer
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->pixAdapter = $pixAdapter;
        $this->requestBuilder = $requestBuilder;
        $this->paymentHelper = $paymentHelper;
        $this->serializer = $serializer;
    }

    /**
     * @return string
     */
    public function getVerificationUrl(): string
    {
        $quote = $this->checkoutSession->getQuote();
        $order = $this->paymentHelper->retrieveOrderForQuote($quote);
        $params = $this->requestBuilder->getBaseVerificationUrlRequestData($order);
        $params['payment_details'] = ['payment_method' => 'pix'];
        $params['customer_details']['national_identifier'] =
            $quote->getPayment()->getAdditionalInformation('national_identifier');

        $response = $this->pixAdapter->authorize($params);

        $order->setVendoPaymentResponseStatus(PaymentMethod::PAYMENT_RESPONSE_STATUS_USE_IN_CRON);
        $order->setRequestObjectVendo($this->serializer->serialize($params));
        $order = $this->paymentHelper->saveOrder($order);

        $this->paymentHelper->createOpenedTransaction($order, $response['transaction']['id']);
        $this->paymentHelper->generateInvoice($order);

        return $response['result']['verification_url'];
    }

    public function capture(OrderPaymentInterface $payment): array
    {
        $params = $this->requestBuilder->getCaptureRequestData($payment);
        $response = $this->pixAdapter->capture($params);

        return $response;
    }

    public function refund(OrderPaymentInterface $payment, $amount = null): array
    {
        $params = $this->requestBuilder->getRefundRequestData($payment, $amount);
        $response = $this->pixAdapter->refund($params);

        return $response;
    }
}
