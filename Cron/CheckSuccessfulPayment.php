<?php

namespace Vendo\Gateway\Cron;

use Psr\Log\LogLevel;
use Vendo\Gateway\Gateway\Vendo;
use Vendo\Gateway\Model\VendoHelpers;
use Magento\Framework\App\ResourceConnection;
use Vendo\Gateway\Model\PaymentMethod;
use Magento\Sales\Model\Order;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Invoice;

class CheckSuccessfulPayment
{
    const TABLE_NAME_ORDER = 'sales_order';
    const COLUMN_FLAG = 'vendo_payment_response_status';
    const RESPONSE_STATUS_OK_ONE = 1;
    const RESPONSE_STATUS_OK_TWO = 2;

    /**
     * @var VendoHelpers
     */
    private $vendoHelpers;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var Vendo
     */
    protected $vendoGateway;

    /**
     * @var PaymentMethod
     */
    protected $paymentMethod;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * Index constructor.
     * @param VendoHelpers $vendoHelpers
     * @param ResourceConnection $resourceConnection
     * @param Vendo $vendoGateway
     * @param PaymentMethod $paymentMethod
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        VendoHelpers             $vendoHelpers,
        ResourceConnection       $resourceConnection,
        Vendo                    $vendoGateway,
        PaymentMethod            $paymentMethod,
        OrderRepositoryInterface $orderRepository
    )
    {
        $this->vendoHelpers = $vendoHelpers;
        $this->resourceConnection = $resourceConnection;
        $this->vendoGateway = $vendoGateway;
        $this->paymentMethod = $paymentMethod;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Used documentation by steps #8 and #9 from => https://docs.vendoservices.com/reference/payment-gateway-3ds-flow
     * 8. You repeat the same Payment API request that you posted in step #3.
     *    - You can either use the credit card details or the payment_details_token that you got in step #4
     *    - Vendo automatically checks if a successful verification has been recorded for this payment
     * 9. You receive the final transaction status from Vendo's API response
     *    - If the status is 1 the transaction was successfully processed
     *    - If the status is 0 the transaction was declined.
     *
     * @return $this
     * @throws \Magento\Framework\Validator\Exception
     */
    public function execute()
    {
        try {
            $this->vendoHelpers->log('Start Cron. File::Line' . __FILE__ . '::' . __LINE__, LogLevel::DEBUG);

            // Get data by new request
            $connection = $this->resourceConnection->getConnection();
            $table = $connection->getTableName(self::TABLE_NAME_ORDER);
            $query = "SELECT * FROM " . $table . " WHERE `" . self::COLUMN_FLAG . "` = " . \Vendo\Gateway\Model\PaymentMethod::PAYMENT_RESPONSE_STATUS_USE_IN_CRON;
            $result = $connection->fetchAll($query);

            if (count($result) > 0) {
                foreach ($result as $item) {
                    if (!empty($item['entity_id']) && !empty($item['request_object_vendo'])) {
                        // Get order_id
                        $orderId = (int)$item['entity_id'];

                        // Get request params.
                        $requestObjectVendo = unserialize($item['request_object_vendo']);

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
                        $this->vendoHelpers->log('Vendo API response step #2 (3DS): ' . json_encode($response), LogLevel::DEBUG);
                        $responseArray = json_decode($response);
                        if (!empty($responseArray)) {
                            if (!empty($responseArray->status)) {
                                // Check response status.
                                if ($responseArray->status === self::RESPONSE_STATUS_OK_ONE || $responseArray->status === self::RESPONSE_STATUS_OK_TWO) {
                                    $this->vendoHelpers->log('Status: ' . $responseArray->status, LogLevel::DEBUG);
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
                                            } catch (\Exception $e) {
                                                $this->vendoHelpers->log($e->getMessage(), LogLevel::ERROR);
                                            }

                                            // Set 'flags' in invoice.
                                            $invoiceDetails = $order->getInvoiceCollection();
                                            foreach ($invoiceDetails as $invoice) {
                                                try {
                                                    $invoice->setState(Invoice::STATE_PAID);
                                                    $invoice->save();
                                                } catch (\Exception $e) {
                                                    $this->vendoHelpers->log($e->getMessage(), LogLevel::ERROR);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $this->vendoHelpers->log('End Cron. File::Line' . __FILE__ . '::' . __LINE__, LogLevel::DEBUG);
        } catch (\Exception $e) {
            $this->vendoHelpers->log($e->getMessage(), LogLevel::ERROR);
        }

        return $this;
    }
}
