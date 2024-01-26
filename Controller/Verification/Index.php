<?php

namespace Vendo\Gateway\Controller\Verification;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\Result\RawFactory;

use Magento\Framework\App\RequestInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Psr\Log\LogLevel;
use Vendo\Gateway\Model\PaymentMethod;
use Vendo\Gateway\Model\VendoHelpers;
use Vendo\Gateway\Gateway\Vendo;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\CsrfAwareActionInterface;
use Vendo\Gateway\Model\PaymentHelper;
use Vendo\Gateway\Gateway\Request\RequestBuilder;

class Index extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    const S2S_RESPONSE_STATUS_OK = 1;
    const S2S_RESPONSE_STATUS_ERROR = 2;
    const TABLE_NAME_ORDER = 'sales_order';
    const RESPONSE_STATUS_OK_ONE = 1;
    const RESPONSE_STATUS_OK_TWO = 2;

    protected $pageFactory;
    protected $resultRawFactory;
    protected $requestInterface;
    protected $vendoHelpers;
    protected $resourceConnection;
    protected $orderRepository;
    protected $paymentMethod;
    protected $vendoGateway;
    protected $paymentHelper;

    protected $requestBuilder;

    public function __construct(
        Context                  $context,
        PageFactory              $pageFactory,
        RawFactory               $resultRawFactory,
        RequestInterface         $requestInterface,
        VendoHelpers             $vendoHelpers,
        ResourceConnection       $resourceConnection,
        OrderRepositoryInterface $orderRepository,
        PaymentMethod            $paymentMethod,
        Vendo                    $vendoGateway,
        PaymentHelper            $paymentHelper,
        RequestBuilder           $requestBuilder
    )
    {
        $this->resultRawFactory = $resultRawFactory;
        $this->requestInterface = $requestInterface;
        $this->vendoHelpers = $vendoHelpers;
        $this->resourceConnection = $resourceConnection;
        $this->orderRepository = $orderRepository;
        $this->paymentMethod = $paymentMethod;
        $this->vendoGateway = $vendoGateway;
        $this->paymentHelper = $paymentHelper;
        $this->requestBuilder = $requestBuilder;
        return parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Raw|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        // Get params for check.
        $params = $this->requestInterface->getParams();

        // Add data to log.
        $this->vendoHelpers->log('Vendo Request Params (S2S): ' . json_encode($params), LogLevel::DEBUG);

        // Default value
        $contentOkOrError = '<code>' . self::S2S_RESPONSE_STATUS_ERROR . '</code><errorMessage>' . __('ERROR: #0001 not request params') . '</errorMessage>'; // Response ERROR
        $responseType = 'verification';

        if (!empty($params['callback']) && !empty($params['merchant_reference'])) {
            $responseType = $params['callback'];
            $connection = $this->resourceConnection->getConnection();
            $table = $connection->getTableName(self::TABLE_NAME_ORDER);
            $query = "SELECT `entity_id` FROM " . $table . " WHERE `increment_id` = " . trim($params['merchant_reference']) . " LIMIT 1";
            $orderId = $connection->fetchOne($query);
            $order = $this->orderRepository->get($orderId);

            if ($responseType == 'verification') {
                if (!empty($params['status'])) {
                    $this->paymentHelper->updateTransactionData(
                        $params['transaction_id'],
                        ['is_closed' => 1]
                    );

                    $request = $this->requestBuilder->getS2sPaymentRequest($order, $params['transaction_id']);
                    $response = $this->vendoGateway->postRequest($request, PaymentMethod::TRANSACTION_URL);
                    $this->vendoHelpers->log('Vendo Response (S2S): ' . json_encode($response), LogLevel::DEBUG);
                    $responseArray = json_decode($response);
                    if (!empty($responseArray) && !empty($responseArray->status)
                        && ($responseArray->status == self::RESPONSE_STATUS_OK_ONE || $responseArray->status == self::RESPONSE_STATUS_OK_TWO)) {
                        $this->vendoHelpers->log('Vendo Status Response (S2S): ' . $responseArray->status, LogLevel::DEBUG);

                        try {
                            if (!empty($responseArray->transaction->amount)) {
                                $order->setBaseTotalPaid(number_format($responseArray->transaction->amount, 4, '.', ''));
                                $order->setTotalPaid(number_format($responseArray->transaction->amount, 4, '.', ''));
                            }
                            $order->setState(Order::STATE_PROCESSING)->setStatus(Order::STATE_PROCESSING);
                            $order->setVendoPaymentResponseStatus(PaymentMethod::PAYMENT_RESPONSE_STATUS_USED_IN_CRON_SUCCESS);
                            $this->orderRepository->save($order);
                            $contentOkOrError = '<code>' . self::S2S_RESPONSE_STATUS_OK . '</code>'; // Response OK
                        } catch (\Exception $e) {
                            $this->vendoHelpers->log($e->getMessage(), LogLevel::ERROR);
                            $contentOkOrError = '<code>' . self::S2S_RESPONSE_STATUS_ERROR . '</code><errorMessage>' . $e->getMessage() . '</errorMessage>'; // Response ERROR
                        }
                    }

                    $order->setVendoPaymentResponseStatus(PaymentMethod::PAYMENT_RESPONSE_STATUS_USED_IN_CRON_SUCCESS);
                    $this->orderRepository->save($order);
                } else {
                    $order->setVendoPaymentResponseStatus(PaymentMethod::PAYMENT_RESPONSE_STATUS_NOT_USE_IN_CRON_SET_IN_S2S);
                    $this->orderRepository->save($order);
                    $this->vendoHelpers->log('Vendo Set vendo_payment_response_status = 5, not use in Cron. (S2S)', LogLevel::DEBUG);
                }
                $contentOkOrError = '<code>' . self::S2S_RESPONSE_STATUS_OK . '</code>';
            }

            if ($params['callback'] == 'transaction') {
                if (!empty($params['transaction_status'])) {
                    $this->paymentHelper
                        ->createOrderTransaction($order, [
                            'txn_id' => $params['transaction_id'],
                            'type' => TransactionInterface::TYPE_CAPTURE,
                            'is_closed' => 1,
                            'parent_txn_id' => $params['original_transaction_id']
                        ]);
                    // Set 'flags' in invoice.
                    $invoiceDetails = $order->getInvoiceCollection();
                    /** @var Invoice $invoice */
                    foreach ($invoiceDetails as $invoice) {
                        try {
                            $invoice->setState(Invoice::STATE_PAID);
                            $invoice->setTransactionId($params['transaction_id']);
                            $invoice->save();
                            $contentOkOrError = '<code>' . self::S2S_RESPONSE_STATUS_OK . '</code>'; // Response OK
                        } catch (\Exception $e) {
                            $this->vendoHelpers->log($e->getMessage(), LogLevel::ERROR);
                            $contentOkOrError = '<code>' . self::S2S_RESPONSE_STATUS_ERROR . '</code><errorMessage>' . $e->getMessage() . '</errorMessage>'; // Response ERROR
                        }
                    }
                } else {
                    $order->setVendoPaymentResponseStatus(PaymentMethod::PAYMENT_RESPONSE_STATUS_NOT_USE_IN_CRON_SET_IN_S2S);
                    $this->orderRepository->save($order);
                    $this->vendoHelpers->log('Vendo Set vendo_payment_response_status = 5, not use in Cron. (S2S)', LogLevel::DEBUG);
                    $contentOkOrError = '<code>' . self::S2S_RESPONSE_STATUS_OK . '</code>';
                }

            }
        }

        // Add data to log.
        $this->vendoHelpers->log('End Vendo (S2S). File::Line' . __FILE__ . '::' . __LINE__, LogLevel::DEBUG);


        $result = $this->resultRawFactory->create();

        // As Example
//        $contentOkOrError = '<code>1</code>'; // Response OK
//        $contentOkOrError = '<code>2</code><errorMessage>' . $e->getMessage() . '</errorMessage>'; // Response ERROR

        $content = '<?xml version="1.0" encoding="UTF-8"?>
<postbackResponse>'
  . '<' . $responseType . '>'
     . $contentOkOrError . '
  </' . $responseType . '>
</postbackResponse>';

        $result->setHeader('Content-Type', 'text/xml');
        $result->setContents($content);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
