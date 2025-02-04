<?php

declare(strict_types=1);

namespace Vendo\Gateway\Model;

use Magento\Customer\Api\Data\GroupInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Magento\Checkout\Model\Session;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface as TransactionBuilder;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Api\SearchCriteriaBuilder;

class PaymentHelper
{
    /**
     * @var OrderInterfaceFactory
     */
    private $orderFactory;

    /**
     * @var array
     */
    private $orders;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var CartManagementInterface
     */
    private $cartManagement;

    /**
     * @var TransactionBuilder
     */
    private $transactionBuilder;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var OrderPaymentRepositoryInterface
     */
    private $paymentRepository;

    /**
     * @var TransactionRepositoryInterface
     */
    private $transactionRepository;

    /**
     * @var InvoiceService
     */
    private $invoiceService;

    /**
     * @var InvoiceRepositoryInterface
     */
    private $invoiceRepository;

    /**
     * @var Transaction
     */
    private $transaction;

    /** @var null SearchCriteriaBuilder */
    private $searchCriteriaBuilder;

    /**
     * @var ?Quote
     */
    private $quote = null;

    public function __construct(
        OrderInterfaceFactory $orderFactory,
        Session $checkoutSession,
        CartManagementInterface $cartManagement,
        TransactionBuilder $transactionBuilder,
        OrderRepositoryInterface $orderRepository,
        OrderPaymentRepositoryInterface $orderPaymentRepository,
        TransactionRepositoryInterface $transactionRepository,
        InvoiceService $invoiceService,
        InvoiceRepositoryInterface $invoiceRepository,
        Transaction $transaction,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->orderFactory = $orderFactory;
        $this->checkoutSession = $checkoutSession;
        $this->cartManagement = $cartManagement;
        $this->transactionBuilder = $transactionBuilder;
        $this->orderRepository = $orderRepository;
        $this->paymentRepository = $orderPaymentRepository;
        $this->transactionRepository = $transactionRepository;
        $this->invoiceService = $invoiceService;
        $this->invoiceRepository = $invoiceRepository;
        $this->transaction = $transaction;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * Get or create an order for quote
     *
     * @param Quote $quote
     * @return OrderInterface|null
     */
    public function retrieveOrderForQuote(Quote $quote): ?OrderInterface
    {
        $quote->reserveOrderId();
        $this->quote = $quote;
        $this->fillCustomerInfoForGuestQuote();


        $orderIncrementId = $quote->getReservedOrderId();
        $order = $this->loadOrderByIncrementId($orderIncrementId);
        $quote->setReservedOrderId($orderIncrementId);

        return $order;
    }

    /**
     * Fill quote with customer data for guest session
     *
     * @return void
     */
    private function fillCustomerInfoForGuestQuote()
    {
        if ($this->quote->getCustomerGroupId() == GroupInterface::NOT_LOGGED_IN_ID) {
            $this->quote->setCustomerId(null);
            $this->quote->setCustomerEmail($this->quote->getBillingAddress()->getEmail());
            if ($this->quote->getCustomerFirstname() === null && $this->quote->getCustomerLastname() === null) {
                $this->quote->setCustomerFirstname($this->quote->getBillingAddress()->getFirstname());
                $this->quote->setCustomerLastname($this->quote->getBillingAddress()->getLastname());
                if ($this->quote->getBillingAddress()->getMiddlename() === null) {
                    $this->quote->setCustomerMiddlename($this->quote->getBillingAddress()->getMiddlename());
                }
            }
            $this->quote->setCustomerIsGuest(true);
            $this->quote->setCustomerGroupId(GroupInterface::NOT_LOGGED_IN_ID);
        }
    }

    /**
     * Retrieve order for quote
     *
     * @param string $incrementId
     * @param bool $useCache
     * @return OrderInterface|mixed|null
     */
    private function loadOrderByIncrementId($incrementId, $useCache = true)
    {
        if (!isset($this->orders))
            $this->orders = [];

        if (empty($incrementId))
            return null;

        if (!empty($this->orders[$incrementId]) && $useCache)
            return $this->orders[$incrementId];

        try
        {
            $orderModel = $this->orderFactory->create();
            try {
                $order = $orderModel->loadByIncrementId($incrementId);
                if (!$order->getData()) {
                    $order = $this->createOrder();
                }
            } catch (NoSuchEntityException $e) {
                $order = $this->createOrder();
            }
            $this->registerOrderInSession($order);

            $this->orders[$incrementId] = $order;
            return $this->orders[$incrementId];
        } catch (\Exception $e) {
            return null;
        }
    }

    private function registerOrderInSession(OrderInterface $order)
    {
        $this->checkoutSession->setData(PaymentMethod::SESSION_ORDER_KEY, $order->getEntityId());
        $this->checkoutSession->setLastQuoteId($this->quote->getId());
        $this->checkoutSession->setLastSuccessQuoteId($this->quote->getId());
        $this->checkoutSession->setLastOrderId($order->getId());
        $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
        $this->checkoutSession->setLastOrderStatus($order->getStatus());
    }

    public function createOrderTransaction(OrderInterface $order, array $data)
    {
        $payment = $order->getPayment();
        $payment->setLastTransId($data['txn_id']);
        $payment->setTransactionId($data['txn_id']);

        // Formatted price
//        $formattedPrice = $order->getBaseCurrency()->formatTxt($order->getGrandTotal());

        // Prepare transaction
        $transaction = $this->transactionBuilder->setPayment($payment)
            ->setOrder($order)
            ->setTransactionId($data['txn_id'])

            ->setFailSafe(true)
            ->build($data['type']);
        $transaction->setIsClosed($data['is_closed']);
        $transaction->setParentTxnId(isset($data['parent_txn_id']) ? $data['parent_txn_id'] : null);

        // Add transaction to payment
//        $payment->addTransactionCommentsToOrder($transaction, __('The authorized amount is %1.', $formattedPrice));
        $payment->setParentTransactionId(null);

        // Save payment, transaction and order
        $this->paymentRepository->save($payment);
        $this->orderRepository->save($order);
        $this->transactionRepository->save($transaction);

        return  $transaction->getTransactionId();
    }

    public function createOpenedTransaction(OrderInterface $order, string $transactionId)
    {
        $params = [
            'txn_id' => $transactionId,
            'is_closed' => 0,
            'type' => TransactionInterface::TYPE_AUTH
        ];

        return $this->createOrderTransaction($order, $params);
    }

    public function updateTransactionData(string $txnId, array $data)
    {
        $sc = $this->searchCriteriaBuilder->addFilter(TransactionInterface::TXN_ID, $txnId)->create();
        $transaction = array_last($this->transactionRepository->getList($sc)->getItems());
        if ($transaction) {
            $transaction->addData($data);
            $this->transactionRepository->save($transaction);
        }
    }

    /**
     * Create opened invoice
     *
     * @param OrderInterface $order
     * @return void
     * @throws LocalizedException
     * @throws \Exception
     */
    public function generateInvoice(OrderInterface $order)
    {
        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->setState(Invoice::STATE_OPEN);
        $invoice->register();
        $this->invoiceRepository->save($invoice);
        $transaction = $this->transaction
            ->addObject($invoice)
            ->addObject($invoice->getOrder());
        $transaction->save();
    }

    /**
     * @return OrderInterface
     * @throws NoSuchEntityException
     * @throws LocalizedException
     *
     * @return OrderInterface
     */
    private function createOrder()
    {
        $this->quote->collectTotals()->save();

        return $this->cartManagement->submit($this->quote);
    }

    public function saveOrder(OrderInterface $order)
    {
        return $this->orderRepository->save($order);
    }
}
