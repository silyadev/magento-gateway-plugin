<?php

declare(strict_types=1);

namespace Vendo\Gateway\Gateway;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Class Config
 * @package Vendo\Gateway\Gateway
 */
class Config
{
    const METHOD = 'vendo_payment';
    const VENDO_GENERIC_CONFIGURATION = 'vendo_generic_configuration';
    /**
     * @var StoreConfigResolver
     */
    private $storeConfigResolver;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    private $urlBuilder;

    public function __construct(
        StoreConfigResolver $storeConfigResolver,
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor,
        UrlInterface $urlBuilder
    ) {
        $this->storeConfigResolver = $storeConfigResolver;
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
        $this->urlBuilder = $urlBuilder;
    }

    public function getMerchantId(int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            'payment/' . self::VENDO_GENERIC_CONFIGURATION . '/merchant_id',
            ScopeInterface::SCOPE_STORE,
            $storeId ?? $this->storeConfigResolver->getStoreId()
        );
    }

    public function getSiteId(int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            'payment/' . self::VENDO_GENERIC_CONFIGURATION . '/site_id',
            ScopeInterface::SCOPE_STORE,
            $storeId ?? $this->storeConfigResolver->getStoreId()
        );
    }

    public function getIsTestMode(int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            'payment/' . self::VENDO_GENERIC_CONFIGURATION . '/is_test',
            ScopeInterface::SCOPE_STORE,
            $storeId ?? $this->storeConfigResolver->getStoreId()
        );
    }

    public function getApiSecret(int $storeId = null): string
    {
        if (!$this->getIsTestMode($storeId)) {
            $path = 'payment/' . self::VENDO_GENERIC_CONFIGURATION . '/api_secret';
        } else {
            $path = 'payment/' . self::VENDO_GENERIC_CONFIGURATION . '/api_secret_tests';
        }

        return (string)$this->encryptor->decrypt($this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE,
            $storeId ?? $this->storeConfigResolver->getStoreId()
        )
        );
    }

    public function getSuccessUrl(): string
    {
        return $this->urlBuilder->getUrl('vendo/callback/index/');
    }
}
