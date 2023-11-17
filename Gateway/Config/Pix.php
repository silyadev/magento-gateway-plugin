<?php

namespace Vendo\Gateway\Gateway\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Config\Config;
use Magento\Store\Model\ScopeInterface;
use Vendo\Gateway\Gateway\Config as VendoGatewayConfig;
use Vendo\Gateway\Gateway\StoreConfigResolver;

class Pix extends Config
{
    public const KEY_ACTIVE = 'active';
    public const CODE = 'vendo_pix';

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
        UrlInterface $urlBuilder,
        $pathPattern = self::DEFAULT_PATH_PATTERN
    ) {
        parent::__construct($scopeConfig, self::CODE, $pathPattern);
        $this->storeConfigResolver = $storeConfigResolver;
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Get if Pix enabled
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
            'method_title',
            $storeId ?? $this->storeConfigResolver->getStoreId()
        );
    }

    public function getMerchantId(int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            'payment/' . VendoGatewayConfig::VENDO_GENERIC_CONFIGURATION . '/merchant_id',
            ScopeInterface::SCOPE_STORE,
            $storeId ?? $this->storeConfigResolver->getStoreId()
        );
    }

    public function getSiteId(int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            'payment/' . VendoGatewayConfig::VENDO_GENERIC_CONFIGURATION . '/site_id',
            ScopeInterface::SCOPE_STORE,
            $storeId ?? $this->storeConfigResolver->getStoreId()
        );
    }

    public function getIsTestMode(int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            'payment/' . VendoGatewayConfig::VENDO_GENERIC_CONFIGURATION . '/is_test',
            ScopeInterface::SCOPE_STORE,
            $storeId ?? $this->storeConfigResolver->getStoreId()
        );
    }

    public function getApiSecret(int $storeId = null): string
    {
        if (!$this->getIsTestMode($storeId)) {
            $path = 'payment/' . VendoGatewayConfig::VENDO_GENERIC_CONFIGURATION . '/api_secret';
        } else {
            $path = 'payment/' . VendoGatewayConfig::VENDO_GENERIC_CONFIGURATION . '/api_secret_tests';
        }

        return (string)$this->encryptor->decrypt($this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE,
            $storeId ?? $this->storeConfigResolver->getStoreId()
        )
        );
    }

    public function getIsMethodConfigured(int $storeId = null): bool
    {
        return $this->isActive($storeId)
            &&
            $this->getMerchantId($storeId)
            && $this->getSiteId($storeId)
            && $this->getApiSecret($storeId);
    }

    public function getSuccessUrl(): string
    {
        return $this->urlBuilder->getUrl('checkout/onepage/success/');
    }
}
