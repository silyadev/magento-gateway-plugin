<?php

namespace Vendo\Gateway\Model\Ui\Pix;

use Magento\Checkout\Model\ConfigProviderInterface;
use Vendo\Gateway\Gateway\Pix;

class ConfigProvider implements ConfigProviderInterface
{
    public const CODE = 'vendo_pix';

    /**
     * @var Pix
     */
    private $config;

    public function __construct(Pix $config)
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
