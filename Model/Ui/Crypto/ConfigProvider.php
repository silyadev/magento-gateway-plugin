<?php

namespace Vendo\Gateway\Model\Ui\Crypto;

use Magento\Checkout\Model\ConfigProviderInterface;
use Vendo\Gateway\Gateway\Config\Crypto;

class ConfigProvider implements ConfigProviderInterface
{
    public const CODE = 'vendo_crypto';

    /**
     * @var Crypto
     */
    private $config;

    public function __construct(Crypto $config)
    {
        $this->config = $config;
    }

    /**
     * @inheritDoc
     */
    public function getConfig()
    {
        if (!$this->config->getIsMethodConfigured()) {
            return [];
        }

        return [
            'payment' => [
                self::CODE => [
                    'isActive' => $this->config->isActive(),
                    'title' => $this->config->getTitle()
                ]
            ]
        ];
    }
}
