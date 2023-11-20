<?php

namespace Vendo\Gateway\Gateway\Command;

use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Vendo\Gateway\Api\CryptoServiceInterface;
use Vendo\Gateway\Model\PaymentHelper;

class CryptoRefundCommand implements CommandInterface
{
    /**
     * @var CryptoServiceInterface
     */
    private $cryptoService;

    /**
     * @var PaymentHelper
     */
    private $paymentHelper;

    public function __construct(CryptoServiceInterface $cryptoService, PaymentHelper $paymentHelper)
    {
        $this->cryptoService = $cryptoService;
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

        $result = $this->cryptoService->refund($payment);
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
