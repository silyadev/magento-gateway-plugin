<?php

namespace Vendo\Gateway\Model;

use Magento\Framework\App\ObjectManager;
use Magento\Sales\Model\Order\Status\HistoryFactory;
use \Magento\Sales\Model\Order;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Class VendoHelpers
 * @package Vendo\Gateway\Model
 */
class VendoHelpers
{

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var HistoryFactory
     */
    protected $orderHistoryFactory;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * VendoHelpers constructor.
     * @param \Psr\Log\LoggerInterface|null $logger
     * @param HistoryFactory $orderHistoryFactory
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        LoggerInterface $logger = null,
        HistoryFactory $orderHistoryFactory,
        OrderRepositoryInterface $orderRepository
    )
    {
        $this->logger = $logger ?: ObjectManager::getInstance()->get(LoggerInterface::class);
        $this->orderHistoryFactory = $orderHistoryFactory;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @param $message
     */
    public function log($message, $level = \Psr\Log\LogLevel::INFO)
    {
        $this->logger->log($level, $message);
    }

    /**
     * Remove personal data from string used for log
     *
     * @param $request
     * @return mixed
     */
    public function obfuscateRequest($request)
    {
        if (isset($request['payment_details']['card_number'])
            && strlen($request['payment_details']['card_number']) >= 6
        ) {
            $cardNr = $request['payment_details']['card_number'];
            $str = substr($cardNr, 0, 6);
            $str = $str . '...' . substr($cardNr, -4);
            $request['payment_details']['card_number'] = $str;
        }
        if (isset($request['payment_details']['cvv'])) {
            $request['payment_details']['cvv'] = '(obfucated)';
        }

        if (!empty($request['api_secret'])) {
            $request['api_secret'] = '(obfuscated)';
        }

        if (!empty($request['shared_secret'])) {
            $request['shared_secret'] = '(obfuscated)';
        }

        return $request;
    }

    /**
     * Add visible on front comment
     *
     * @param Order $order
     * @param string $comment
     */
    public function addOrderCommentForCustomer($order, $comment, $isVisibleOnFront = true)
    {

        try {
            $status = null;
            if ($order->canComment()) {
                $history = $this->orderHistoryFactory->create()
                    ->setStatus(!empty($status) ? $status : $order->getStatus())
                    ->setEntityName(Order::ENTITY)
                    ->setComment(
                        __('Comment: %1.', $comment)
                    );

                $history->setIsCustomerNotified($isVisibleOnFront)
                    ->setIsVisibleOnFront($isVisibleOnFront);

                $order->addStatusHistory($history); // Add your comment to order
                $this->orderRepository->save($order);
            }
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
        }
    }

    /**
     * Add non visible on front comment
     *
     * @param Order $order
     * @param string $comment
     */
    public function addOrderCommentForAdmin($order, $comment)
    {
        $this->addOrderCommentForCustomer($order, $comment, false);
    }

    /**
     * Validate card type for payment token
     *
     * @param $cardNumber
     * @return string
     */
    public function inferCardType($cardNumber)
    {

        $types = [
            'visa' => "/^4[0-9]{0,15}$/i",
            'mastercard' => "/^5[1-5][0-9]{5,}|222[1-9][0-9]{3,}|22[3-9][0-9]{4,}|2[3-6][0-9]{5,}|27[01][0-9]{4,}|2720[0-9]{3,}$/i",
            'amex' => "/^3$|^3[47][0-9]{0,13}$/i",
            'discover' => "/^6$|^6[05]$|^601[1]?$|^65[0-9][0-9]?$|^6(?:011|5[0-9]{2})[0-9]{0,12}$/i",
            'jcb' => "/^(?:2131|1800|35[0-9]{3})[0-9]{3,}$/i",
            'dinersclub' => "/^3(?:0[0-5]|[68][0-9])[0-9]{4,}$/i",
        ];
        foreach ($types as $brand => $pattern) {
            if (preg_match($pattern, $cardNumber)) {
                return $brand;
            }
        }
        return 'card';
    }
}
