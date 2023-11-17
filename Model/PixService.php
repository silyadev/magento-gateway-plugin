<?php

namespace Vendo\Gateway\Model;

class PixService implements \Vendo\Gateway\Api\PixServiceInterface
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var \Vendo\Gateway\Adapter\Pix
     */
    private $pixAdapter;

    /**
     * @var BasicService
     */
    private $service;

    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Vendo\Gateway\Adapter\Pix $pixAdapter,
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
        $quote->reserveOrderId();
        $params = $this->service->getBaseVerificationUrlRequestData($quote);
        $params['payment_details'] = ['payment_method' => 'pix'];
        $params['customer_details']['national_identifier'] =
            $quote->getPayment()->getAdditionalInformation('national_identifier');

        $response = $this->pixAdapter->authorize($params);

        return $response['result']['verification_url'];
    }
}
