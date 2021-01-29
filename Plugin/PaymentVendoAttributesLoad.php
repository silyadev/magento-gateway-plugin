<?php

namespace Vendo\Gateway\Plugin;

use Magento\Sales\Api\Data\OrderPaymentExtensionInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionFactory;

/**
 * Class PaymentVendoAttributesLoad
 * @package Vendo\Gateway\Plugin
 */
class PaymentVendoAttributesLoad
{
    /**
     * @var OrderPaymentExtensionFactory
     */
    protected $paymentExtensionFactory;

    public function __construct(
        OrderPaymentExtensionFactory $paymentExtensionFactory
    )
    {
        $this->paymentExtensionFactory = $paymentExtensionFactory;
    }

    /**
     * Load vault payment extension attribute to order/payment entity
     *
     * @param OrderPaymentInterface $payment
     * @param OrderPaymentExtensionInterface|null $paymentExtension
     * @return OrderPaymentExtensionInterface
     */
    public function afterGetExtensionAttributes(
        OrderPaymentInterface $payment,
        OrderPaymentExtensionInterface $paymentExtension = null
    )
    {
        if ($paymentExtension === null) {
            $paymentExtension = $this->paymentExtensionFactory->create();
        }

        $paymentToken = $paymentExtension->getSepaPaymentToken();
        if ($paymentToken === null) {
            $paymentToken = $payment->getTransactionAdditionalInfo('sepa_payment_token');
            if ($paymentToken) {
                $paymentExtension->setSepaPaymentToken($paymentToken);
            }
        }


        $sepaDetails = $paymentExtension->getSepaPaymentMandate();
        if ($sepaDetails === null) {
            $paymentToken = $payment->getTransactionAdditionalInfo('sepa_details');
            if ($sepaDetails) {
                $paymentExtension->setSepaPaymentMandate($sepaDetails);
            }
        }






        $payment->setExtensionAttributes($paymentExtension);

        return $paymentExtension;
    }
}
