<?php

declare(strict_types=1);

namespace Vendo\Gateway\Gateway\Command;

use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\CommandInterface;
use Vendo\Gateway\Api\CryptoServiceInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Api\Data\InvoiceInterface;

class CryptoCaptureCommand implements CommandInterface
{
    /**
     * @var CryptoServiceInterface
     */
    private $cryptoService;

    private $request;

    public function __construct(CryptoServiceInterface $cryptoService, RequestInterface $request)
    {
        $this->cryptoService = $cryptoService;
        $this->request = $request;
    }

    /**
     * @inheritDoc
     */
    public function execute(array $commandSubject)
    {
        /** @var OrderPaymentInterface $payment */
        $payment = $commandSubject['payment']->getPayment();
        $payment->getLastTransId();
        if ($invoiceId = $this->request->getParam('invoice_id')) {
            /** @var InvoiceInterface $invoice */
            $invoice = $payment->getOrder()->getInvoiceCollection()->getItemById($invoiceId);
            $invoice->pay();
//            $invoice->getTransactionId();
        } else {
            $invoices = $payment->getOrder()->getInvoiceCollection();
            foreach ($invoices as $invoice) {
                $invoice->pay();
            }
        }


//        $result = $this->cryptoService->capture($payment);
//        //$requestId = $result['request_id'];
//        if ($result['status'] == 1) {
////            $payment->getLastTransId();
//        } else {
//            throw new CommandException(__('Capturing failed. Response code %1, message: %2', $result["error"]["code"], $result["error"]["message"]));
//        }
    }
}
