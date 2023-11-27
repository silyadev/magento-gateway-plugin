<?php

namespace Vendo\Gateway\Controller\Callback;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\PaymentAdapterInterface;
use Magento\Sales\Model\ResourceModel\Order\Invoice\Collection;
use Psr\Log\LogLevel;
use \Vendo\Gateway\Model\PaymentMethod;
use \Vendo\Gateway\Model\Sepa;
use Vendo\Gateway\Model\Ui\Crypto\ConfigProvider;
use Vendo\Gateway\Model\VendoHelpers;
use Magento\Sales\Model\Order\Invoice;
use Vendo\Gateway\Model\Ui\Pix\ConfigProvider as PixConfigProvider;
use Vendo\Gateway\Model\Ui\Crypto\ConfigProvider as CryptoConfigProvider;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;

/**
 * Class Index
 * @package Vendo\Gateway\Controller\Callback
 */
class Index extends Action
{
    /**
     * @var PageFactory
     */
    protected $_pageFactory;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var ResultFactory
     */
    protected $resultFactory;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var VendoHelpers
     */
    private $vendoHelpers;

    /**
     * @var Registry
     */
    protected $_registry;

    /**
     * @var RedirectInterface
     */
    protected $redirect;

    /**
     * @var OrderManagementInterface
     */
    protected $orderManagement;

    /**
     * @var InvoiceSender
     */
    protected $invoiceSender;

    /**
     * Index constructor.
     * @param Context $context
     * @param PageFactory $pageFactory
     * @param Session $checkoutSession
     * @param OrderRepositoryInterface $orderRepository
     * @param ResultFactory $resultFactory
     * @param ManagerInterface $messageManager
     * @param VendoHelpers $vendoHelpers
     * @param Registry $registry
     * @param RedirectInterface $redirect
     * @param OrderManagementInterface $orderManagement
     */
    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        Session $checkoutSession,
        OrderRepositoryInterface $orderRepository,
        ResultFactory $resultFactory,
        ManagerInterface $messageManager,
        VendoHelpers $vendoHelpers,
        Registry $registry,
        RedirectInterface $redirect,
        OrderManagementInterface $orderManagement,
        InvoiceSender $invoiceSender
    )
    {
        $this->_pageFactory = $pageFactory;
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->resultFactory = $resultFactory;
        $this->messageManager = $messageManager;
        $this->vendoHelpers = $vendoHelpers;
        $this->_registry = $registry;
        $this->redirect = $redirect;
        $this->orderManagement = $orderManagement;
        $this->invoiceSender = $invoiceSender;
        return parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     */
    public function execute()
    {
        if ($this->checkoutSession->getData(PaymentMethod::RETRY_KEY)) {
            return $this->redirectCart('We were unable to process your order, please try again.', true);
        } else {
            $this->checkoutSession->setData(PaymentMethod::RETRY_KEY, 1);
        }


        echo "redirecting...";
        //todo: how to identify request is secure? use session params for now, ask question
        try {
//            $refererUrl = $this->redirect->getRefererUrl();
//            $redirectUrl = $this->redirect->getRedirectUrl();

            $orderId = $this->checkoutSession->getData(PaymentMethod::SESSION_ORDER_KEY);
            if (!$orderId) {
                $this->redirectCart('Order id is missed');
            }

            $order = $this->orderRepository->get($orderId);
            $incrementId = $order->getIncrementId();
            $payment = $order->getPayment();

            $methodCode = $payment->getMethod();
            if (!in_array($methodCode, [PaymentMethod::CODE, Sepa::CODE, PixConfigProvider::CODE, CryptoConfigProvider::CODE])) {
                $this->redirectCart('Wrong payment method');
            }

            $invoice_details = $order->getInvoiceCollection();
            if ($methodCode == PaymentMethod::CODE || $methodCode == Sepa::CODE) {
                $token = $payment->getAdditionalInformation('sepa_payment_token');
                if (!$token) {
                    $token = $payment->getExtensionAttributes()->getVaultPaymentToken()->getGatewayToken();
                    if (!$token) {
                        $this->redirectCart('No payment token');
                    }
                }


                foreach ($invoice_details as $invoice) {
                    if ($invoice->getState() == Invoice::STATE_OPEN) {
                        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
                        $invoice->capture();
                        $invoice->save();
                    }
                }
            }

            if ($methodCode == PixConfigProvider::CODE || $methodCode == CryptoConfigProvider::CODE) {
                foreach ($invoice_details as $invoice) {
                    if (!$invoice->getEmailSent()) {
                        $this->invoiceSender->send($invoice);
                    }
                }
            }


            if ($order->getStatus() === Order::STATE_PROCESSING) {
                $this->checkoutSession
                    ->setLastOrderId($orderId)
                    ->setLastRealOrderId($incrementId);
                $this->vendoHelpers->addOrderCommentForAdmin($payment->getOrder(), "Verification completed");
                $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
                $redirect->setUrl('/checkout/onepage/success');
                $this->checkoutSession->setData(PaymentMethod::RETRY_KEY, null);
                $this->checkoutSession->setData(PaymentMethod::SESSION_ORDER_KEY, null);
                $this->checkoutSession->setData(PaymentMethod::SESSION_ORDER_INC_KEY, null);

                return $redirect;
            }

            if ($order->getStatus() === Order::STATE_PAYMENT_REVIEW) {
                if ($url = $this->_registry->registry('verification_url')) {
                    return $this->redirectCart("The payment verification failed, please try again.", true);
                }
            }
        } catch (\Exception $e) {
            $this->vendoHelpers->log($e->getMessage(), LogLevel::ERROR);
            return $this->redirectCart("Error during payment process");
        }

        return $this->redirectCart();
    }

    /**
     * Redirect to the cart page and cancel order in case of exception
     * @param null $errorMessage
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function redirectCart($errorMessage = null, $returnQuote = null)
    {
        //TODO: refill cart and cancel order?
        if ($errorMessage) {
            $this->vendoHelpers->log($errorMessage, LogLevel::ERROR);
            $this->messageManager->addErrorMessage(__($errorMessage));

            if ($returnQuote) {
                $this->returnCustomerQuote(true, $errorMessage);
            }
        }

        $this->checkoutSession->setData(PaymentMethod::RETRY_KEY, null);
        $this->checkoutSession->setData(PaymentMethod::SESSION_ORDER_KEY, null);
        $this->checkoutSession->setData(PaymentMethod::SESSION_ORDER_INC_KEY, null);

        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $redirect->setUrl('/checkout/cart');
        return $redirect;
    }

    /**
     * Return customer quote and cancel order in case of exception
     * @param false $cancelOrder
     * @param string $errorMsg
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function returnCustomerQuote($cancelOrder = false, $errorMsg = '')
    {
        $incrementId = $this->checkoutSession->getData(PaymentMethod::SESSION_ORDER_INC_KEY);
        if ($incrementId) {
            $order = $this->_objectManager->create(\Magento\Sales\Model\Order::class)->loadByIncrementId($incrementId);
            if ($order->getId()) {
                try {
                    $quoteRepository = $this->_objectManager->create(\Magento\Quote\Api\CartRepositoryInterface::class);
                    $quote = $quoteRepository->get($order->getQuoteId());

                    $quote->setIsActive(1)->setReservedOrderId(null);
                    $quoteRepository->save($quote);
                    $this->_objectManager->get(\Magento\Checkout\Model\Session::class)->replaceQuote($quote);
                } catch (\Exception $e) {
                }
                if ($cancelOrder) {
                    $methodInstance  = $order->getPayment()->getMethodInstance();
                    $this->vendoHelpers->addOrderCommentForAdmin($order, $methodInstance::CANCEL_MESSAGE);
                    $this->vendoHelpers->log($methodInstance::CANCEL_MESSAGE, LogLevel::ERROR);
                    $invoice_details = $order->getInvoiceCollection();
                    foreach ($invoice_details as $invoice) {
                        if ($invoice->getState() == Invoice::STATE_OPEN) {
                            $invoice->cancel();
                            $invoice->save();
                        }
                    }
                    $this->orderManagement->cancel($order->getId());
                    $order->save();
                    $this->orderManagement->cancel($order->getEntityId());
                }
            }
        }
    }

}
