<?php

namespace Vendo\Gateway\Model;

use Magento\Checkout\Model\Session;
use Vendo\Gateway\Adapter\Crypto;
use Vendo\Gateway\Api\CryptoServiceInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Vendo\Gateway\Gateway\Request\RequestBuilder;

class CryptoService implements CryptoServiceInterface
{
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var Crypto
     */
    private $cryptoAdapter;

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
        Crypto $cryptoAdapter,
        RequestBuilder $requestBuilder,
        PaymentHelper $paymentHelper
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->cryptoAdapter = $cryptoAdapter;
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
        $params['payment_details'] = ['payment_method' => 'crypto'];

        $response = $this->cryptoAdapter->authorize($params);

        $this->paymentHelper->createOpenedTransaction($order, $response['transaction']['id']);
        $this->paymentHelper->generateInvoice($order);

        return $response['result']['verification_url'];
    }

    public function capture(OrderPaymentInterface $payment): array
    {
        $params = $this->requestBuilder->getCaptureRequestData($payment);
        $response = $this->cryptoAdapter->capture($params);

        return $response;
    }

    public function refund(OrderPaymentInterface $payment, $amount = null): array
    {
        $params = $this->requestBuilder->getRefundRequestData($payment, $amount);
        $response = $this->cryptoAdapter->refund($params);

        return $response;
    }
}
