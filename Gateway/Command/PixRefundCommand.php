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
        $a = 1;
        /** @var OrderPaymentInterface $payment */
        $payment = $commandSubject['payment']->getPayment();
        $amount = isset($commandSubject['amount']) ?: null;

        $result = $this->pixService->refund($payment, $amount);
        //$requestId = $result['request_id'];
        if ($result['status'] == 1) {
//            $this->paymentHelper->createOrderTransaction($payment->getOrder(),
//                [
//                    'txn_id' => $result,
//                    'is_closed' => 0,
//                    'type' => TransactionInterface::TYPE_AUTH
//                ]
//            );
//            $payment->getOrder()->void
        } else {
            throw new CommandException(__('Refund failed. Response code %1, message: %2', $result["error"]["code"], $result["error"]["message"]));
        }
    }
}
