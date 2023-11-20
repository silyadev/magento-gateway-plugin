<?php

namespace Vendo\Gateway\Model;

use Magento\Checkout\Model\Session;
use Vendo\Gateway\Adapter\Crypto;
use Vendo\Gateway\Api\CryptoServiceInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

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
     * @var BasicService
     */
    private $service;

    public function __construct(
        Session $checkoutSession,
        Crypto $cryptoAdapter,
        BasicService $service
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->cryptoAdapter = $cryptoAdapter;
        $this->service = $service;
    }

    /**
     * @return string
     */
    public function getVerificationUrl(): string
    {
        $quote = $this->checkoutSession->getQuote();
        $order = $this->service->retrieveOrderForQuote($quote);

        $params = $this->service->getBaseVerificationUrlRequestData($order);
        $params['payment_details'] = ['payment_method' => 'crypto'];

        $response = $this->cryptoAdapter->authorize($params);

        $this->service->generateTransaction($order, $response['transaction']['id']);
        $this->service->generateInvoice($order);

        return $response['result']['verification_url'];
    }

    public function capture(OrderPaymentInterface $payment): array
    {
        $params = $this->service->getCaptureRequestData($payment);
        $response = $this->cryptoAdapter->capture($params);

        return $response;
    }

    public function refund(OrderPaymentInterface $payment, $amount = null): array
    {
        $params = $this->service->getRefundRequestData($payment, $amount);
        $response = $this->cryptoAdapter->refund($params);

        return $response;
    }
}
