<?php

namespace Vendo\Gateway\Gateway\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Config\Config;
use Vendo\Gateway\Gateway\StoreConfigResolver;
use Vendo\Gateway\Gateway\Config as GeneralConfig;

class Pix extends Config
{
    public const KEY_ACTIVE = 'active';
    public const CODE = 'vendo_pix';
    public const KEY_TITLE = 'method_title';

    /**
     * @var StoreConfigResolver
     */
    private $storeConfigResolver;

    /**
     * @var GeneralConfig
     */
    private $generalConfig;

    public function __construct(
        StoreConfigResolver $storeConfigResolver,
        ScopeConfigInterface $scopeConfig,
        GeneralConfig $generalConfig,
        $pathPattern = self::DEFAULT_PATH_PATTERN
    ) {
        parent::__construct($scopeConfig, self::CODE, $pathPattern);
        $this->storeConfigResolver = $storeConfigResolver;
        $this->generalConfig = $generalConfig;
    }

    /**
     * Get if Crypto enabled
     *
     * @param int|null $storeId
     * @return bool
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function isActive(int $storeId = null): bool
    {
        return (bool) $this->getValue(
            self::KEY_ACTIVE,
            $storeId ?? $this->storeConfigResolver->getStoreId()
        );
    }

    public function getTitle(int $storeId = null): string
    {
        return (string)$this->getValue(
            self::KEY_TITLE,
            $storeId ?? $this->storeConfigResolver->getStoreId()
        );
    }

    public function getIsMethodConfigured(int $storeId = null): bool
    {
        return $this->isActive($storeId)
            &&
            $this->generalConfig->getMerchantId($storeId)
            && $this->generalConfig->getSiteId($storeId)
            && $this->generalConfig->getApiSecret($storeId);
    }
}
