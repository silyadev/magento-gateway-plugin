<?php

namespace Vendo\Gateway\Model;

class CryptoService implements \Vendo\Gateway\Api\CryptoServiceInterface
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var \Vendo\Gateway\Adapter\Crypto
     */
    private $cryptoAdapter;

    /**
     * @var BasicService
     */
    private $service;

    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Vendo\Gateway\Adapter\Crypto $cryptoAdapter,
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
        $quote->reserveOrderId();
        $params = $this->service->getBaseVerificationUrlRequestData($quote);
        $params['payment_details'] = ['payment_method' => 'crypto'];

        $response = $this->cryptoAdapter->authorize($params);

        return $response['result']['verification_url'];
    }
}
