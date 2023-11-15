<?php

namespace Vendo\Gateway\Service;

use Magento\Framework\Session\SessionManagerInterface;
use Magento\Checkout\Model\Session;

class GetReservedOrderIncrementId
{
    public const RESERVER_ORDER_INCREMENT_ID_SESSION_KEY = 'reserver_order_increment_id_key';

    private $checkoutSession;

    private $sessionManager;

    public function __construct(
        Session $checkoutSession,
        SessionManagerInterface $sessionManager
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->sessionManager = $sessionManager;
    }

    public function execute()
    {
        $orderIncrementIdFromSession = $this->sessionManager->getData(
            self::RESERVER_ORDER_INCREMENT_ID_SESSION_KEY,
            false
        );
        if (!$orderIncrementIdFromSession) {
            $reservedOrderIncrementIdFromQuote = (string) $this->checkoutSession->getQuote()->getReservedOrderId();
            $this->sessionManager->setData(
                self::RESERVER_ORDER_INCREMENT_ID_SESSION_KEY, $reservedOrderIncrementIdFromQuote
            );
            return $reservedOrderIncrementIdFromQuote;
        }
        return $orderIncrementIdFromSession;
    }
}
