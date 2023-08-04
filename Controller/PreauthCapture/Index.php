<?php

namespace Vendo\Gateway\Controller\PreauthCapture;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LogLevel;
use Vendo\Gateway\Gateway\Vendo;
use Vendo\Gateway\Model\PaymentMethod;
use Vendo\Gateway\Model\VendoHelpers;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;

class Index extends Action
{
    const RESPONSE_STATUS_OK = 1;
    protected $context;
    protected $orderRepository;
    protected $vendoHelpers;
    protected $requestInterface;
    protected $resultRedirectFactory;
    protected $messageManager;
    protected $paymentMethod;

    public function __construct(
        Context                  $context,
        OrderRepositoryInterface $orderRepository,
        VendoHelpers             $vendoHelpers,
        RequestInterface         $requestInterface,
        RedirectFactory          $resultRedirectFactory,
        MessageManagerInterface  $messageManager,
        PaymentMethod            $paymentMethod,
        Vendo                    $vendoGateway,
    )
    {
        $this->orderRepository = $orderRepository;
        $this->vendoHelpers = $vendoHelpers;
        $this->requestInterface = $requestInterface;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->messageManager = $messageManager;
        $this->paymentMethod = $paymentMethod;
        $this->vendoGateway = $vendoGateway;
        return parent::__construct($context);
    }

    public function execute()
    {
        $params = $this->requestInterface->getParams();
        if (!empty($params['order_id'])) {
            $orderId = (int)$params['order_id'];
            $order = $this->orderRepository->get($orderId);
            if (!empty($order)) {
                $payment = $order->getPayment();
                if (!empty($payment)) {
                    $transactionId = $payment->getLastTransId();
                    if (!empty($transactionId)) {
                        if (!empty($order->getRequestObjectVendo())) {
                            // Get request params.
                            $requestObjectVendo = unserialize($order->getRequestObjectVendo());

                            // Set request params
                            $request = $this->paymentMethod->_prepareBasicGatewayData();

                            // Set to request all params.
                            foreach ($requestObjectVendo as $k => $v) {
                                $str = $k;
                                $str = str_replace('_', ' ', $str);
                                $str = ucwords($str);
                                $str = str_replace(' ', '', $str);
                                $str = 'set' . $str;
                                if ($str == 'setPreauthOnly') continue;
                                $request->{$str}($v);
                            }
                            $request->setTransactionId($transactionId);

                            // Get response from API.
                            $response = $this->vendoGateway->postRequest($request, \Vendo\Gateway\Model\PaymentMethod::TRANSACTION_CAPTURE_URL);
                            $this->vendoHelpers->log('Vendo Capture response step #2: ' . json_encode($response), LogLevel::DEBUG);
                            $responseArray = json_decode($response);
                            if (!empty($responseArray)) {
                                if (!empty($responseArray->status)) {
                                    // Check response status.
                                    if ($responseArray->status === self::RESPONSE_STATUS_OK) {
                                        try {
                                            //$order->setState(Order::STATE_PROCESSING)->setStatus(Order::STATE_PROCESSING);
                                            $order->setVendoPaymentResponseStatus(\Vendo\Gateway\Model\PaymentMethod::PAYMENT_RESPONSE_STATUS_USED_IN_VENDO_CAPTURE_SUCCESS);
                                            $order->save();
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

        // Add message.
        $this->messageManager->addSuccess(__("Success Capture Vendo"));

        // Redirect to back Order view.
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setUrl($_SERVER['HTTP_REFERER']);
        return $resultRedirect;
    }
}
