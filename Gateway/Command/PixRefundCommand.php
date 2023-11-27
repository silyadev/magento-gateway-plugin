<?php

namespace Vendo\Gateway\Gateway\Command;

use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Vendo\Gateway\Api\PixServiceInterface;
use Vendo\Gateway\Model\PaymentHelper;

class PixRefundCommand implements CommandInterface
{
    /**
     * @var PixServiceInterface
     */
    private $pixService;

    /**
     * @var PaymentHelper
     */
    private $paymentHelper;

    public function __construct(PixServiceInterface $pixService, PaymentHelper $paymentHelper)
    {
        $this->pixService = $pixService;
        $this->paymentHelper = $paymentHelper;
    }

    /**
     * @inheritDoc
     */
    public function execute(array $commandSubject)
    {
        /** @var OrderPaymentInterface $payment */
        $payment = $commandSubject['payment']->getPayment();
        $amount = isset($commandSubject['amount']) ?: null;

        $result = $this->pixService->refund($payment, $amount);
        if ($result['status'] == 1) {
            throw new CommandException(__('Refund failed. Response code %1, message: %2', $result["error"]["code"], $result["error"]["message"]));
        }
    }
}
