<?php

declare(strict_types=1);

namespace Vendo\Gateway\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Magento\Checkout\Model\Session;
use Magento\Quote\Api\CartManagementInterface;

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

    public function __construct(
        OrderInterfaceFactory $orderFactory,
        Session $checkoutSession,
        CartManagementInterface $cartManagement
    ) {
        $this->orderFactory = $orderFactory;
        $this->checkoutSession = $checkoutSession;
        $this->cartManagement = $cartManagement;
    }

    /**
     * Retrieve order for quote
     *
     * @param string $incrementId
     * @param bool $useCache
     * @return OrderInterface|mixed|null
     */
    public function loadOrderByIncrementId($incrementId, $useCache = true)
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
            } catch (NoSuchEntityException $e) {
                $order = $this->createOrder();
            }

            $this->orders[$incrementId] = $order;
            return $this->orders[$incrementId];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @return OrderInterface
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    private function createOrder()
    {
        $quote = $this->checkoutSession->getQuote();
        $quote->collectTotals()->save();

        return $this->cartManagement->submit($quote);
    }
}
