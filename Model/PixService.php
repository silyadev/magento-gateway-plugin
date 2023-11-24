<?php

namespace Vendo\Gateway\Model;

use Magento\Checkout\Model\Session;
use Vendo\Gateway\Adapter\Pix;
use Vendo\Gateway\Api\PixServiceInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Vendo\Gateway\Gateway\Request\RequestBuilder;

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

    public function __construct(
        Session $checkoutSession,
        Pix $pixAdapter,
        RequestBuilder $requestBuilder,
        PaymentHelper $paymentHelper
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->pixAdapter = $pixAdapter;
        $this->requestBuilder = $requestBuilder;
        $this->paymentHelper = $paymentHelper;
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
