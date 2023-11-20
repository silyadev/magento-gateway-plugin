<?php

declare(strict_types=1);

namespace Vendo\Gateway\Gateway\Command;

use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\CommandInterface;
use Vendo\Gateway\Api\PixServiceInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class PixCaptureCommand implements CommandInterface
{
    /**
     * @var PixServiceInterface
     */
    private $pixService;

    public function __construct(PixServiceInterface $pixService)
    {
        $this->pixService = $pixService;
    }

    /**
     * @inheritDoc
     */
    public function execute(array $commandSubject)
    {
        /** @var OrderPaymentInterface $payment */
        $payment = $commandSubject['payment']->getPayment();
        $result = $this->pixService->capture($payment);
        //$requestId = $result['request_id'];
        if ($result['status'] == 1) {
//            $payment->getLastTransId();
        } else {
            throw new CommandException(__('Capturing failed. Response code %1, message: %2', $result["error"]["code"], $result["error"]["message"]));
        }
        $a = 1;
    }
}
