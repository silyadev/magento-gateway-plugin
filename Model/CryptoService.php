<?php

namespace Vendo\Gateway\Model;

use Vendo\Gateway\Api\CryptoServiceInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\Locale\ResolverInterface;
use Vendo\Gateway\Gateway\Crypto;
use Vendo\Gateway\Adapter\Crypto as CryptoAdapter;

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
