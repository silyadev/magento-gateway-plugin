<?php

namespace Vendo\Gateway\Model;

use Magento\Directory\Model\CountryFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException as LocalizedExceptionAlias;
use Magento\Framework\HTTP\Header;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Model\Context;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Logger;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LogLevel;
use Magento\Payment\Model\InfoInterface;
use \Magento\Payment\Model\Method\Cc;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Vendo\Gateway\Gateway\Config as VendoGatewayConfig;
use Vendo\Gateway\Gateway\Vendo;
use Magento\Checkout\Model\Session;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;

class Sepa extends PaymentMethod
{

    const CODE = 'vendo_sepa';

    protected $_code = self::CODE;

    protected $_supportedCurrencyCodes = array('EUR');

    public const CANCEL_MESSAGE = "The order will be cancelled automatically because of failed or cancelled SEPA verification";

    /**
     * @var Resolver
     */
    private $localeResolver;

    /**
     * @var Header
     */
    private $httpHeader;

    /**
     * @var RemoteAddress
     */
    private $remoteAddress;

    /**
     * @var VendoHelpers
     */
    private $vendoHelpers;

    /**
     * @var PaymentTokenFactoryInterface
     */
    private $paymentTokenFactory;

    /**
     * @var OrderPaymentExtensionInterfaceFactory
     */
    private $paymentExtensionFactory;

    /**
     * @var PaymentTokenRepositoryInterface
     */
    private $paymentTokenRepository;

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * Sepa constructor.
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param Data $paymentData
     * @param ScopeConfigInterface $scopeConfig
     * @param Logger $logger
     * @param ModuleListInterface $moduleList
     * @param TimezoneInterface $localeDate
     * @param CountryFactory $countryFactory
     * @param EncryptorInterface $encryptor
     * @param Vendo $_vendoGateway
     * @param Resolver $localeResolver
     * @param Header $httpHeader
     * @param RemoteAddress $remoteAddress
     * @param VendoHelpers $vendoHelpers
     * @param PaymentTokenFactoryInterface $paymentTokenFactory
     * @param OrderPaymentExtensionInterfaceFactory $paymentExtensionFactory
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param Session $checkoutSession
     * @param PaymentTokenRepositoryInterface $paymentTokenRepository
     * @param UrlInterface $urlBuilder
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        Data $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        ModuleListInterface $moduleList,
        TimezoneInterface $localeDate,
        CountryFactory $countryFactory,
        EncryptorInterface $encryptor,
        Vendo $_vendoGateway,
        Resolver $localeResolver,
        Header $httpHeader,
        RemoteAddress $remoteAddress,
        VendoHelpers $vendoHelpers,
        PaymentTokenFactoryInterface $paymentTokenFactory,
        OrderPaymentExtensionInterfaceFactory $paymentExtensionFactory,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
        Session $checkoutSession,
        PaymentTokenRepositoryInterface $paymentTokenRepository,
        UrlInterface $urlBuilder,
        array $data = []
    )
    {

        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $moduleList,
            $localeDate,
            $countryFactory,
            $encryptor,
            $_vendoGateway,
            $localeResolver,
            $httpHeader,
            $remoteAddress,
            $vendoHelpers,
            $paymentTokenFactory,
            $paymentExtensionFactory,
            $cookieManager,
            $cookieMetadataFactory,
            $checkoutSession,
            $urlBuilder,
            $data
        );

        $this->localeResolver = $localeResolver;
        $this->httpHeader = $httpHeader;
        $this->remoteAddress = $remoteAddress;
        $this->vendoHelpers = $vendoHelpers;
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->paymentExtensionFactory = $paymentExtensionFactory;
        $this->paymentTokenRepository = $paymentTokenRepository;
    }

    /**
     * Payment capturing
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Validator\Exception
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->vendoHelpers->log('Processing SEPA payment...', LogLevel::DEBUG);
        $this->deleteCookie();
        $extensionAttributes = $this->getExtensionAttributes($payment);

        if ($payment->getAdditionalInformation('sepa_payment_token')) {
            $token = $payment->getAdditionalInformation('sepa_payment_token');
            $request = $this->_prepareBasicGatewayData();
            $request->setAmount(round($amount, 2));
            $request = $this->_prepareCardDetails($request, $payment, $amount);
            $request->setOrigid($payment->getAdditionalInformation(self::PNREF));
            $order = $payment->getOrder();
            $request->setCurrency($order->getBaseCurrencyCode());
            $request = $this->_prepareCustomerItemsData($request, $payment);
            $request = $this->_addRequestOrderInfo($request, $order);
            $payment->unsAdditionalInformation(self::PNREF);
            $request->unsPaymentDetails();
            $request->unsCustomerDetails();
            $request->setPaymentDetails(['token' => $token]);
            $request = $this->setRequestDetails($request);
        } else {
            $request = $this->_prepareGatewayData($payment, $amount);
            $request->setAmount(round($amount, 2));
            $order = $payment->getOrder();
            $request->setCurrency($order->getBaseCurrencyCode());
            $request = $this->_prepareCustomerItemsData($request, $payment);
            $request = $this->_addRequestOrderInfo($request, $order);
        }

        $request = $this->_addRequestOrderInfo($request, $payment->getOrder());

        $this->vendoHelpers->log('API Endpoint: ' . self::TRANSACTION_URL);
        $this->vendoHelpers->log('API json: ' . json_encode($this->vendoHelpers->obfuscateRequest($request->getData())));

        $response = $this->_vendoGateway->postRequest($request, self::TRANSACTION_URL);

        $this->vendoHelpers->log('Vendo API RESPONSE: ' . json_encode($response), LogLevel::DEBUG);

        $this->_processErrors($response);
        $this->_setTranssactionStatus($payment, $response, $request->getData());

        return $this;
    }



    /**
     * Return request object with basic information Of Card Details
     *
     * @return DataObject
     */
    public function _prepareCardDetails($request, $payment, $amount)
    {
        $additionalInformation = $payment->getData('additional_information');
        $sepaDetails = array_key_exists('cc_details', $additionalInformation) ? $additionalInformation['cc_details'] : [];
        $sepaDetails = new DataObject($sepaDetails);
        $paymentDetails = [];
        $paymentDetails['payment_method'] = 'sepa';
        $paymentDetails['iban'] = $sepaDetails->getData('sepa_iban');
        $paymentDetails['bic_swift'] = ($sepaDetails->getSepaBicSwift()) ?: 'XXXXXX12';
        $request->setPaymentDetails($paymentDetails);

        return $request;
    }

    /**
     * Process result code of non-failed request
     *
     * @param $payment
     * @param $response
     * @param $requestParams
     * @return mixed
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Stdlib\Cookie\CookieSizeLimitReachedException
     * @throws \Magento\Framework\Stdlib\Cookie\FailureToSendException
     */
    public function _setTranssactionStatus($payment, $response, $requestParams)
    {
        $body = json_decode($response, true);

        switch ($body['status']) {
            case self::RESPONSE_CODE_APPROVED:
                $extensionAttributes = $this->getExtensionAttributes($payment);
                if (!$extensionAttributes->getSepaPaymentToken()) {
                    $this->_saveSepaPaymentToken($payment, $body, $requestParams);
                } else {
                    $this->_saveSepaPaymentToken($payment, $body, $requestParams, true);
                }
                $payment->setTransactionId($body['transaction']['id'])->setIsTransactionClosed(true);
                try {
                    $payment->getOrder()->setState(Order::STATE_PROCESSING)->setStatus(Order::STATE_PROCESSING);
                    $payment->getOrder()->setVendoPaymentResponseStatus(\Vendo\Gateway\Model\PaymentMethod::PAYMENT_RESPONSE_STATUS_NOT_USE_IN_CRON); // Save flag for use in cron job.
                    $payment->getOrder()->save();
                } catch (\Exception $e) {
                    $this->vendoHelpers->log($e->getMessage(), LogLevel::ERROR);
                }
                break;
            case self::RESPONSE_CODE_VERIFICATION_REQUIRED:
                $message = 'Risk Rules Verification required. Redirecting to Vendo\'s verification endpoint';
                if (array_key_exists('code', $body['result'])) {
                    $message .= " | ".$body['result']['code'] . ': ';
                }
                if (array_key_exists('message', $body['result'])) {
                    $message .= $body['result']['message'];
                }
                $this->vendoHelpers->log(
                    $message,
                    LogLevel::DEBUG);
                $this->vendoHelpers->addOrderCommentForAdmin($payment->getOrder(), $message);

                $this->_saveSepaPaymentToken($payment, $body, $requestParams);
                $payment->setTransactionId($body['transaction']['id'])->setIsTransactionClosed(false);
                $payment->setIsTransactionPending(true);
                //TODO: no email should be sent
                $payment->getOrder()->setCustomerNoteNotify(false);
                $metadata = $this->cookieMetadataFactory
                    ->createPublicCookieMetadata()
                    ->setDuration(self::COOKIE_DURATION)
                    ->setPath('/')->setHttpOnly(false);
                $this->cookieManager
                    ->setPublicCookie(self::COOKIE_NAME, $body['result']['verification_url'], $metadata);
                $this->_registry->unregister('verification_url');
                $this->_registry->register('verification_url', $body['result']['verification_url']);

                $this->checkoutSession->setData(self::SESSION_ORDER_KEY, $payment->getOrder()->getId());
                $this->checkoutSession->setData(self::SESSION_ORDER_INC_KEY, $payment->getOrder()->getIncrementId());
                $this->vendoHelpers->addOrderCommentForAdmin($payment->getOrder(), "Redirecting..." . $body['result']['verification_url']);
                // Save flag for use in cron job.
                // 'vendo_payment_response_status' => 1 => not use job
                // 'vendo_payment_response_status' => 2 => use in cron job => Vendo checks if a successful verification has been recorded for this payment.
                try {
                    $payment->getOrder()->setVendoPaymentResponseStatus(\Vendo\Gateway\Model\PaymentMethod::PAYMENT_RESPONSE_STATUS_USE_IN_CRON);
                    $payment->getOrder()->setRequestObjectVendo(serialize($requestParams));
                    $payment->getOrder()->save();
                } catch (\Exception $e) {
                    $this->vendoHelpers->log($e->getMessage(), LogLevel::ERROR);
                }
                break;
            default:
                break;
        }

        return $payment;
    }

    /**
     * Add error messages depending on vendo server response
     *
     * @param $body
     * @return string
     */
    private function _buildErrorMessage($body)
    {
        $errorMessage = null;
        if ($body['error']['code'] == self::RESPONSE_CODE_INVALID_JSON_REQUEST
            || $body['error']['code'] == self::RESPONSE_CODE_INVALID_API_CREDENTIALS
            || $body['error']['code'] == self::RESPONSE_CODE_PARAMETER_VALIDATION_ERROR
        ) {
            $errorMessage = __('Your order cannot be confirmed, please try again.') .
                ' ' . __('If you keep getting this error please report it to us.');
        } else {
            $errorMessage = $body['error']['code'] . ': ' . $body['error']['message'];
            if (!empty($body['error']['processor_status'])) {
                $errorMessage = __('Your payment was rejected - ') . $errorMessage;
            } else {
                $errorMessage = __('Payment error - ') . $errorMessage;
            }
        }
        if ($errorMessage) {
            $this->vendoHelpers->log('Payment Error: ' . $errorMessage, LogLevel::ERROR);
            $errorMessage = self::GENERIC_ERROR_MESSAGE;
        }

        return __($errorMessage);
    }

    /**
     * Save payment token to payment extension attributes
     * @param $payment
     * @param array $body
     * @param array $requestParams
     * @param false $update
     */
    private function _saveSepaPaymentToken($payment, $body, $requestParams, $update = false)
    {
        $extensionAttributes = $this->getExtensionAttributes($payment);

        if ($update) {
            $paymentToken = $extensionAttributes->getSepaPaymentToken();
        } else {
            if (!array_key_exists('payment_details_token', $body)) {
                return;
            }
            $extensionAttributes->setSepaPaymentToken($body['payment_details_token']);
            $payment->setAdditionalInformation(
                'sepa_payment_token',
                $body['payment_details_token']
            );
            $payment->setTransactionAdditionalInfo('sepa_payment_token', $body['payment_details_token']);
        }

        if (array_key_exists('sepa_details', $body)) {
            $sepaDetails = new DataObject($body['sepa_details']);
            $details = json_encode([
                'mandate_id' => $sepaDetails->getMandateId(),
                'mandate_signed_date' => $sepaDetails->getMandateSignedDate(),
            ]);
            $this->vendoHelpers->addOrderCommentForAdmin($payment->getOrder(), $details);
            $this->vendoHelpers->log('Sepa Details: ' . $details);
//            $paymentToken->setTokenDetails($details);
            $extensionAttributes->setSepaPaymentMandate($details);
            $payment->setTransactionAdditionalInfo('sepa_payment_mandate', $details);
            $payment->setAdditionalInformation(
                'sepa_payment_mandate', $details
            );

        }
    }

    /**
     * Get payment extension attributes
     *
     * @param InfoInterface $payment
     * @return OrderPaymentExtensionInterface
     */
    private function getExtensionAttributes(InfoInterface $payment)
    {
        $extensionAttributes = $payment->getExtensionAttributes();
        if ($extensionAttributes === null) {
            $extensionAttributes = $this->paymentExtensionFactory->create();
            $payment->setExtensionAttributes($extensionAttributes);
        }
        return $extensionAttributes;
    }

    /**
     * Retrieve payment method title
     *
     * @return string
     * @deprecated 100.2.0
     */
    public function getTitle()
    {
        return $this->getConfigData('method_title', null, false);
    }

    /**
     * @return $this|Sepa
     * @throws LocalizedExceptionAlias
     */
    public function validate()
    {
        /*
         * calling parent validate function
         */
        AbstractMethod::validate();

        $info = $this->getInfoInstance();
        $errorMsg = false;

        if ($errorMsg) {
            throw new \Magento\Framework\Exception\LocalizedException($errorMsg);
        }

        return $this;
    }

    /**
     * Assign data to info model instance
     *
     * @param \Magento\Framework\DataObject|mixed $data
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        AbstractMethod::assignData($data);

        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_object($additionalData)) {
            $additionalData = new DataObject($additionalData ?: []);
        }

        /** @var DataObject $info */
        $info = $this->getInfoInstance();
        $info->addData(
            [
                'sepa_iban' => $additionalData->getSepaIban(),
                'sepa_bic_swift' => ($additionalData->getSepaBicSwift()) ?: 'XXXXXX12'
            ]
        );

        return $this;
    }

    /**
     * Method availability fix (credit card validation is not needed in SEPA)
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        return AbstractMethod::isAvailable($quote);
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param string $field
     * @param int|string|null|\Magento\Store\Model\Store $storeId
     *
     * @return mixed
     * @deprecated 100.2.0
     */
    public function getConfigData($field, $storeId = null, $fromParentMethod = true)
    {
        if ('order_place_redirect_url' === $field) {
            return $this->getOrderPlaceRedirectUrl();
        }
        if ('payment_action' == $field) {
            return parent::getConfigData($field, $storeId);
        }
        if (null === $storeId) {
            $storeId = $this->getStore();
        }
        if ($fromParentMethod) {
            $path = 'payment/' . VendoGatewayConfig::VENDO_GENERIC_CONFIGURATION . '/' . $field;
        } else {
            $path = 'payment/' . $this->getCode() . '/' . $field;
        }

        return $this->_scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }

}
