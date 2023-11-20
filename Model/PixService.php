<?php

namespace Vendo\Gateway\Model;

use Magento\Checkout\Model\Session;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Vendo\Gateway\Adapter\Pix;
use Vendo\Gateway\Api\PixServiceInterface;

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
     * @var BasicService
     */
    private $service;

    public function __construct(
        Session $checkoutSession,
        Pix $pixAdapter,
        BasicService $service
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->pixAdapter = $pixAdapter;
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
        $params['payment_details'] = ['payment_method' => 'pix'];
        $params['customer_details']['national_identifier'] =
            $quote->getPayment()->getAdditionalInformation('national_identifier');

        $response = $this->pixAdapter->authorize($params);

        $this->service->generateTransaction($order, $response['transaction']['id']);
        $this->service->generateInvoice($order);

        return $response['result']['verification_url'];
    }

    public function capture(OrderPaymentInterface $payment): array
    {
        $params = $this->service->getCaptureRequestData($payment);
        $response = $this->pixAdapter->capture($params);

        return $response;
    }

    public function refund(OrderPaymentInterface $payment, $amount = null): array
    {
        $params = $this->service->getCaptureRequestData($payment, $amount);
        $response = $this->pixAdapter->refund($params);

        return $response;
    }
}
