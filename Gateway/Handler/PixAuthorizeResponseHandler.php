<?php

namespace Vendo\Gateway\Gateway\Handler;

use Magento\Payment\Gateway\Response\HandlerInterface;

class PixAuthorizeResponseHandler implements HandlerInterface
{

    /**
     * @inheritDoc
     */
    public function handle(array $handlingSubject, array $response)
    {
        $status = $response['status'];
        $verificationUrl = $response['result']['verification_url'];
        $transactionId = $response['transaction']['id'];
        // TODO: implement response handle method: create magento transaction, redirect and save status
    }
}
