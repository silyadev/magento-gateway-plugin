<?php

declare(strict_types=1);

namespace Vendo\Gateway\Gateway;

use Magento\AuthorizenetAcceptjs\Model\Adminhtml\Source\Environment;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Class Config
 * @package Vendo\Gateway\Gateway
 */
class Config extends \Magento\Payment\Gateway\Config\Config
{
    const METHOD = 'vendo_payment';
    const VENDO_GENERIC_CONFIGURATION = 'vendo_generic_configuration';

}
