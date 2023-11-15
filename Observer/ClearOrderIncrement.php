<?php

namespace Vendo\Gateway\Observer;

use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Vendo\Gateway\Service\GetReservedOrderIncrementId;

class ClearOrderIncrement implements ObserverInterface
{
    private $sessionManager;

    public function __construct(SessionManagerInterface $sessionManager)
    {
        $this->sessionManager = $sessionManager;
    }

    public function execute(Observer $observer)
    {
        $this->sessionManager->getData(
            GetReservedOrderIncrementId::RESERVER_ORDER_INCREMENT_ID_SESSION_KEY,
            true
        );
    }
}
