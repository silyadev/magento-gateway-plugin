<?php
namespace Vendo\Gateway\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;
use Vendo\Gateway\Model\PaymentMethod;

/**
 * Class SepaAddData
 * @package Vando\Gateway\Observer
 */
class SepaAddData extends AbstractDataAssignObserver
{
    /**
     * @var array
     */
    private $sepaKeys = [
        'sepa_iban',
        'sepa_bic_swift'
    ];

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $dataObject = $this->readDataArgument($observer);

        $additionalData = $dataObject->getData(PaymentInterface::KEY_ADDITIONAL_DATA);

        if (!is_array($additionalData)) {
            return;
        }

        $sepaData = array_intersect_key($additionalData, array_flip($this->sepaKeys));
        if (count($sepaData) !== count($this->sepaKeys)) {
            return;
        }
        $paymentModel = $this->readPaymentModelArgument($observer);

        $paymentModel->setAdditionalInformation(
            PaymentMethod::CC_DETAILS,
            $this->sortSepaData($sepaData)
        );

        // CC data should be stored explicitly
        foreach ($sepaData as $ccKey => $ccValue) {
            $paymentModel->setData($ccKey, $ccValue);
        }
    }

    /**
     * @param array $ccData
     * @return array
     */
    private function sortSepaData(array $sepaData)
    {
        $r = [];
        foreach ($this->sepaKeys as $key) {
            $r[$key] = isset($sepaData[$key]) ? $sepaData[$key] : null;
        }

        return $r;
    }
}
