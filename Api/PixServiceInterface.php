<?php

namespace Vendo\Gateway\Api;

use Magento\Sales\Api\Data\OrderPaymentInterface;

interface PixServiceInterface
{
    /**
     * @return string
     */
    public function getVerificationUrl(): string;

    /**
     * @param OrderPaymentInterface $payment
     * @return array
     */
    public function capture(OrderPaymentInterface $payment): array;

    /**
     * @param OrderPaymentInterface $payment
     * @param $amount
     * @return array
     */
    public function refund(OrderPaymentInterface $payment, $amount = null): array;
}
