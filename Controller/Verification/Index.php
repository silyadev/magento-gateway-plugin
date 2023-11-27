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

class Index extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    const S2S_RESPONSE_STATUS_OK = 1;
    const S2S_RESPONSE_STATUS_ERROR = 2;

    protected $pageFactory;
    protected $resultRawFactory;
    protected $requestInterface;
    protected $vendoHelpers;
    protected $resourceConnection;
    protected $orderRepository;
    protected $paymentMethod;
    protected $vendoGateway;
    protected $paymentHelper;

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
        PaymentHelper            $paymentHelper
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
        return parent::__construct($context);
    }

    public function execute()
    {
        // Get params for check.
        $params = $this->requestInterface->getParams();

        // Add data to log.
        $this->vendoHelpers->log('Vendo Request Params (S2S): ' . json_encode($params), LogLevel::DEBUG);

        // Default value
        $contentOkOrError = '<code>' . self::S2S_RESPONSE_STATUS_ERROR . '</code><errorMessage>' . __('ERROR: #0001 not request params') . '</errorMessage>'; // Response ERROR
        $responseType = 'verification';

        if ((!empty($params['callback']) && $params['callback'] == 'verification') || (!empty($params['callback']) && $params['callback'] == 'transaction') ) {
            $responseType = $params['callback'];
            if (!empty($params['transaction_id']) && !empty($params['merchant_reference'])) { // Order ID
                if (isset($params['status']) || isset($params['is_test'])) {

                    // Logic:
                    // If status == 0 => set 'sales_order.vendo_payment_response_status' = 5 => not use in Crone => response XML (OK) => write log.
                    // If status == 1 => use full Cron logic => response XML (OK or ERROR) => write log.

                    // Set connection.
                    $connection = $this->resourceConnection->getConnection();
                    $table = $connection->getTableName(\Vendo\Gateway\Cron\CheckSuccessfulPayment::TABLE_NAME_ORDER);

                    if ((isset($params['status']) && $params['status'] == 1) || (isset($params['transaction_status']) && $params['transaction_status'] == 1)) { // 1 = Verification was successful
                        // If status == 1 => use full Cron logic => response XML (OK or ERROR) => write log.

                        try {
                            // Get data from table 'sales_order'.
                            $query = "SELECT * FROM " . $table . " WHERE `increment_id` = " . trim($params['merchant_reference']) . " LIMIT 1";
                            $result = $connection->fetchAll($query);

                            // Begin Logic as Corn 'CheckSuccessfulPayment.php' ****************************************
                            if (!empty($result[0]['entity_id']) && !empty($result[0]['request_object_vendo'])) {
                                // Get order_id
                                $orderId = (int)$result[0]['entity_id'];

                                // Get request params.
                                $requestObjectVendo = unserialize($result[0]['request_object_vendo']);

                                // Set request params
                                $request = $this->paymentMethod->_prepareBasicGatewayData();

                                // Set to request all params.
                                foreach ($requestObjectVendo as $k => $v) {
                                    $str = $k;
                                    $str = str_replace('_', ' ', $str);
                                    $str = ucwords($str);
                                    $str = str_replace(' ', '', $str);
                                    $str = 'set' . $str;
                                    $request->{$str}($v);
                                }
                                //$this->vendoHelpers->log('In cron $requestObjectVendo: ' . var_export($requestObjectVendo, true), LogLevel::DEBUG); // For check.
                                //$this->vendoHelpers->log('In cron $request: ' . var_export($request, true), LogLevel::DEBUG); // For check.

                                // Get response from API.
                                $response = $this->vendoGateway->postRequest($request, \Vendo\Gateway\Model\PaymentMethod::TRANSACTION_URL);
                                $this->vendoHelpers->log('Vendo Response (S2S): ' . json_encode($response), LogLevel::DEBUG);
                                $responseArray = json_decode($response);
                                if (!empty($responseArray)) {
                                    if (!empty($responseArray->status)) {
                                        // Check response status.
                                        if ($responseArray->status === \Vendo\Gateway\Cron\CheckSuccessfulPayment::RESPONSE_STATUS_OK_ONE || $responseArray->status === \Vendo\Gateway\Cron\CheckSuccessfulPayment::RESPONSE_STATUS_OK_TWO) {
                                            $this->vendoHelpers->log('Vendo Status Response (S2S): ' . $responseArray->status, LogLevel::DEBUG);
                                            // Set all flags (invoice, order).
                                            if (!empty($orderId)) {
                                                // Get order.
                                                $order = $this->orderRepository->get($orderId);

                                                if (!empty($order)) {
                                                    // Set 'flags' in order.
                                                    try {
                                                        if (!empty($responseArray->transaction->amount)) {
                                                            $order->setBaseTotalPaid(number_format($responseArray->transaction->amount, 4, '.', ''));
                                                            $order->setTotalPaid(number_format($responseArray->transaction->amount, 4, '.', ''));
                                                        }
                                                        $order->setState(Order::STATE_PROCESSING)->setStatus(Order::STATE_PROCESSING);
                                                        $order->setVendoPaymentResponseStatus(\Vendo\Gateway\Model\PaymentMethod::PAYMENT_RESPONSE_STATUS_USED_IN_CRON_SUCCESS);
                                                        $order->save();
                                                        $contentOkOrError = '<code>' . self::S2S_RESPONSE_STATUS_OK . '</code>'; // Response OK
                                                    } catch (\Exception $e) {
                                                        $this->vendoHelpers->log($e->getMessage(), LogLevel::ERROR);
                                                        $contentOkOrError = '<code>' . self::S2S_RESPONSE_STATUS_ERROR . '</code><errorMessage>' . $e->getMessage() . '</errorMessage>'; // Response ERROR
                                                    }

                                                    if ($params['callback'] == 'verification') {
                                                        $this->paymentHelper->updateTransactionData(
                                                            $params['transaction_id'],
                                                            ['is_closed' => 1]
                                                        );
                                                    }

                                                    if ($params['callback'] == 'transaction') {
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
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            // End  Logic as Corn 'CheckSuccessfulPayment.php' *****************************************
                        } catch (\Exception $e) {
                            $this->vendoHelpers->log($e->getMessage(), LogLevel::ERROR);
                            $contentOkOrError = '<code>' . self::S2S_RESPONSE_STATUS_ERROR . '</code><errorMessage>' . $e->getMessage() . '</errorMessage>'; // Response ERROR
                        }
                    } elseif ((isset($params['status']) && (isset($params['status']) && $params['status'] == 0))|| $params['transaction_status'] == 0) { // 0 = Verification failed
                        // If status == 0 => set 'sales_order.vendo_payment_response_status' = 5 => not use in Crone => response XML (OK) => write log.

                        try {
                            // Get data from table 'sales_order'.
                            $query = "SELECT * FROM " . $table . " WHERE `increment_id` = " . trim($params['merchant_reference']) . " LIMIT 1";
                            $result = $connection->fetchAll($query);
                            if (!empty($result[0]['entity_id'])) {
                                // Get order_id
                                $orderId = (int)$result[0]['entity_id'];

                                // Set flag invoice to order.
                                if (!empty($orderId)) {
                                    // Get order.
                                    $order = $this->orderRepository->get($orderId);

                                    if (!empty($order)) {
                                        // Set 'flags' in order.
                                        try {
                                            $order->setVendoPaymentResponseStatus(\Vendo\Gateway\Model\PaymentMethod::PAYMENT_RESPONSE_STATUS_NOT_USE_IN_CRON_SET_IN_S2S);
                                            $order->save();
                                            $contentOkOrError = '<code>' . self::S2S_RESPONSE_STATUS_OK . '</code>'; // Response OK

                                            $this->vendoHelpers->log('Vendo Set vendo_payment_response_status = 5, not use in Cron. (S2S)', LogLevel::DEBUG);
                                        } catch (\Exception $e) {
                                            $this->vendoHelpers->log($e->getMessage(), LogLevel::ERROR);
                                            $contentOkOrError = '<code>' . self::S2S_RESPONSE_STATUS_ERROR . '</code><errorMessage>' . $e->getMessage() . '</errorMessage>'; // Response ERROR
                                        }
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            $this->vendoHelpers->log($e->getMessage(), LogLevel::ERROR);
                            $contentOkOrError = '<code>' . self::S2S_RESPONSE_STATUS_ERROR . '</code><errorMessage>' . $e->getMessage() . '</errorMessage>'; // Response ERROR
                        }
                    }
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
