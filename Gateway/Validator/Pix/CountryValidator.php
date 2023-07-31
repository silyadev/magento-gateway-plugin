<?php

namespace Vendo\Gateway\Gateway\Validator\Pix;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Vendo\Gateway\Gateway\Pix;

class CountryValidator extends AbstractValidator
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
        $availableCountries = explode(
            ',',
            $this->paymentConfig->getValue('specificcountry', $storeId)
        );
        $isAvailable = in_array($validationSubject['country'], $availableCountries);

        return $this->createResult($isAvailable);
    }
}
