<?php

namespace Vendo\Gateway\Gateway\Validator\Pix;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Vendo\Gateway\Gateway\Pix;


class AvailabilityValidator extends AbstractValidator
{
    /**
     * @var Pix
     */
    private $paymentConfig;

    public function __construct(ResultInterfaceFactory $resultFactory, Pix $paymentConfig)
    {
        $this->paymentConfig = $paymentConfig;
        parent::__construct($resultFactory);
    }

    /**
     * @inheritDoc
     */
    public function validate(array $validationSubject)
    {
        $storeId = $validationSubject['storeId'];
        $isAvailable = $this->paymentConfig->getIsMethodConfigured($storeId);

        return $this->createResult($isAvailable);
    }
}
