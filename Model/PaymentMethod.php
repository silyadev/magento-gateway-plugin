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
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\Method\Logger;
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
use Vendo\Gateway\Gateway\Vendo;
use Magento\Checkout\Model\Session;


class PaymentMethod extends Cc
{

    const CODE = 'vendo_payment';
    const PAYMENT_RESPONSE_STATUS_NOT_USE_IN_CRON = 1;
    const PAYMENT_RESPONSE_STATUS_USE_IN_CRON = 2;
    const PAYMENT_RESPONSE_STATUS_USED_IN_CRON_SUCCESS = 3;

    protected $_code = self::CODE;

    const CC_DETAILS = 'cc_details';

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isGateway = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canAuthorize = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canCapture = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canCapturePartial = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canVoid = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canUseInternal = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canUseCheckout = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canSaveCc = false;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isProxy = false;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canFetchTransactionInfo = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canReviewPayment = true;

    /**
     * Gateway request timeout
     *
     * @var int
     */
    protected $_clientTimeout = 45;

    protected $_countryFactory;

    /**
     * https://docs.vendoservices.com/reference/payment-gateway#body-payment-gateway
     * Valid values: USD, EUR, GBP, JPY
     */
    protected $_supportedCurrencyCodes = array('USD', 'EUR', 'GBP', 'JPY');

    /**
     * @var EncryptorInterface
     */
    protected $_encryptor;

    public const TRANSACTION_URL = 'https://secure.vend-o.com/api/payment';

    public const REFUND_URL = 'https://secure.vend-o.com/api/gateway/refund';

    public const RESPONSE_CODE_INVALID_JSON_REQUEST = 8102;

    public const RESPONSE_CODE_INVALID_API_CREDENTIALS = 8103;

    public const RESPONSE_CODE_PARAMETER_VALIDATION_ERROR = 8105;

    public const SESSION_ORDER_KEY = 'VENDO-3DS-ORDER-ID';

    public const SESSION_ORDER_INC_KEY = 'VENDO-3DS-ORDER-INCREMENT-ID';

    public const RETRY_KEY = 'VENDO-3DS-ORDER-RETRY';

    /**
     * 1 if the transaction was successful.
     * 0 if the transaction failed.
     * 2 if the transaction needs further verification (see Appendix A and the result element)
     */
    public const RESPONSE_CODE_APPROVED = 1;

    public const RESPONSE_CODE_VERIFICATION_REQUIRED = 2;

    /**
     * actionType
     * Two types: 0 = 'only refund' 1 = 'refund + cancel subscription'
     */
    public const REFUND_ACTION_ONLY_REFUND = 0;

    public const REFUND_ACTION_REFUND_CANCEL_SUBSCRIPTION = 1;

    public const CANCEL_MESSAGE = "The order will be cancelled automatically because of failed or cancelled 3ds verification";

    public const GENERIC_ERROR_MESSAGE = "Your payment was not processed, please try again. If you keep getting this error then contact us.";

    /**
     * @var Vendo
     */
    protected $_vendoGateway;

    const PNREF = 'pnref';

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isInitializeNeeded = false;

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

    const COOKIE_NAME = 'vendo_verification_url';

    const COOKIE_DURATION = 120;

    /**
     * @var CookieManagerInterface
     */
    protected $cookieManager;

    /**
     * @var CookieMetadataFactory
     */
    protected $cookieMetadataFactory;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var string
     */
    protected $_infoBlockType = \Vendo\Gateway\Block\Payment\Info::class;

    /**
     * PaymentMethod constructor.
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
            null,
            null,
            $data
        );

        $this->_countryFactory = $countryFactory;
        $this->_scopeConfig = $scopeConfig;

        $this->_encryptor = $encryptor;
        $this->_vendoGateway = $_vendoGateway;
        $this->localeResolver = $localeResolver;
        $this->httpHeader = $httpHeader;
        $this->remoteAddress = $remoteAddress;
        $this->vendoHelpers = $vendoHelpers;
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->paymentExtensionFactory = $paymentExtensionFactory;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->checkoutSession = $checkoutSession;
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
        $this->vendoHelpers->log('Processing payment...', LogLevel::DEBUG);
        $this->deleteCookie();
        $extensionAttributes = $this->getExtensionAttributes($payment);

        if ($extensionAttributes->getVaultPaymentToken()) {
            $token = $extensionAttributes->getVaultPaymentToken()->getGatewayToken();
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
     * @param InfoInterface $payment
     * @param float $amount
     * @return PaymentMethod
     * @throws LocalizedExceptionAlias
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        /**
         * Vendo only supports capture and refund at the moment.
         */
        return parent::authorize($payment, $amount);
    }

    /**
     * @param InfoInterface $payment
     * @param float $amount
     * @return $this|PaymentMethod
     * @throws LocalizedExceptionAlias
     * @throws \Magento\Framework\Validator\Exception
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if ($amount <= 0) {
            throw new LocalizedExceptionAlias(__('Invalid amount for refund.'));
        }

        if (!$payment->getParentTransactionId()) {
            throw new LocalizedExceptionAlias(__('Invalid transaction ID.'));
        }

        if ($payment->getAmountRefunded() && (round($payment->getAmountPaid(), 2) > (round($amount, 2) + round($payment->getAmountRefunded(), 2)))) {
            /**
             * Just to be clear a transaction can have only one partial refund, if later you want to refund
             * the remaining you should trigger a full refund (without sending the partial_amount param),
             * that second refund will process the remaining automatically.
             */

            throw new LocalizedExceptionAlias(__(
                'You can do only one partial refund. The second one should have full remaining amount.'
            ));
        }

        $this->vendoHelpers->log('Processing refund...', LogLevel::DEBUG);

        $request = $this->_prepareBasicGatewayData();
        $request->setTransactionId($payment->getParentTransactionId());

        /**
         * We have seen that you are doing the refunds always with the partial_amount param being set,
         * we recommend to use the partial_amount only if you are doing a partial refund.
         *
         * If the refund should be for the total amount of the original transaction please don't send the partial_amount param.
         */
        if (round($payment->getAmountPaid(), 2) > (round($amount, 2) + round($payment->getAmountRefunded(), 2))) {
            $request->setPartialAmount(round($amount, 2));
        }


        $this->vendoHelpers->log('API Endpoint: ' . self::REFUND_URL);
        $this->vendoHelpers->log('API json: ' . json_encode($this->vendoHelpers->obfuscateRequest($request->getData())));

        $response = $this->_vendoGateway->postRequest($request, self::REFUND_URL);

        $this->vendoHelpers->log('Vendo API RESPONSE: ' . json_encode($response), LogLevel::DEBUG);

        $body = json_decode($response, true);
        if ($body['status'] == self::RESPONSE_CODE_APPROVED) {
            $payment->setTransactionId($body['transaction_id'])->setIsTransactionClosed(true);
        } else {
            $errorMessage = $this->_buildErrorMessage($body);
            throw new LocalizedExceptionAlias($errorMessage);
        }
        return $this;
    }

    /**
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {

        if ($this->getConfigData('is_test') && !$this->getConfigData('api_secret_tests')) {
            return false;
        }

        if (!$this->getConfigData('is_test') && !$this->getConfigData('api_secret')) {
            return false;
        }

        return parent::isAvailable($quote);
    }


    /**
     * Availability for currency
     *
     * @param string $currencyCode
     * @return bool
     */
    public function canUseForCurrency($currencyCode)
    {
        if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
            return false;
        }

        return true;
    }

    /**
     * Void payment
     * @param InfoInterface $payment
     * @return PaymentMethod
     * @throws LocalizedExceptionAlias
     */
    public function void(\Magento\Payment\Model\InfoInterface $payment)
    {
        return parent::void($payment);
    }

    /**
     * Check void availability
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function canVoid()
    {
        if ($this->getInfoInstance()->getAmountPaid()) {
            $this->_canVoid = false;
        }

        return $this->_canVoid;
    }

    /**
     * Attempt to void the authorization on cancelling
     *
     * @param InfoInterface|Object $payment
     * @return $this
     */
    public function cancel(\Magento\Payment\Model\InfoInterface $payment)
    {
        if (!$payment->getOrder()->getInvoiceCollection()->count()) {
            return $this->void($payment);
        }

        return false;
    }

    /**
     * Add customer data to request
     *
     * @param DataObject $order
     * @param DataObject $request
     * @return DataObject
     */
    public function fillCustomerContacts(DataObject $order, DataObject $request)
    {
        $billing = $order->getBillingAddress();
        if (!empty($billing)) {
            $request = $this->setBilling($request, $billing);
            $request->setEmail($order->getCustomerEmail());
        }

        $shipping = $order->getShippingAddress();
        if (!empty($shipping)) {
            $request = $this->setShipping($request, $shipping);
            return $request;
        }

        return $request;
    }

    /**
     * Return request object with basic information Of Card Details
     *
     * @return DataObject
     */
    public function _prepareCardDetails($request, $payment, $amount)
    {
        $paymentDetails = [];
        $paymentDetails['payment_method'] = 'card';
        $paymentDetails['card_number'] = $payment->getCcNumber();
        $paymentDetails['expiration_month'] = sprintf('%02d', $payment->getCcExpMonth());
        $paymentDetails['expiration_year'] = $payment->getCcExpYear();
        $paymentDetails['cvv'] = $payment->getCcCid();

        $billing = $payment->getOrder()->getBillingAddress();
        $paymentDetails['name_on_card'] = $billing->getFirstname() . $billing->getLastname();

        $request->setPaymentDetails($paymentDetails);

        return $request;
    }

    /**
     * Add shipping address data to request
     *
     * @param DataObject $request
     * @param DataObject $shipping
     *
     * @return Object
     */
    public function _setShippingAddress($request, $shipping)
    {
        $shippingAddress = [
            'first_name' => $shipping->getFirstname(),
            'last_name' => $shipping->getLastname(),
            'address' => implode(' ', $shipping->getStreet()),
            'city' => $shipping->getCity(),
            'state' => $shipping->getRegionCode(),
            'country' => $shipping->getCountryId(),
            'postal_code' => $shipping->getPostcode(),
            'phone' => $shipping->getTelephone(),
        ];
        $request->setShippingAddress($shippingAddress);
        return $request;
    }

    /**
     * Add billing address data to request
     *
     * @param DataObject $request
     * @param DataObject $billing
     *
     * @return Object
     */
    public function _setBilling($request, $billing)
    {
        $billingAddress = [
            'first_name' => $billing->getFirstname(),
            'last_name' => $billing->getLastname(),
            'language' => $this->getCurrentLocale(),
            'address' => implode(' ', $billing->getStreet()),
            'city' => $billing->getCity(),
            'state' => $billing->getRegionCode(),
            'country' => $billing->getCountryId(),
            'postal_code' => $billing->getPostcode(),
            'phone' => $billing->getTelephone(),
            'email' => $billing->getCustomerEmail(),
        ];
        $request->setCustomerDetails($billingAddress);

        return $request;
    }

    /**
     * Add address/order items data to request
     *
     * @param DataObject $payment
     * @param DataObject $request
     * @return DataObject
     */
    public function _prepareCustomerItemsData($request, $payment)
    {
        $order = $payment->getOrder();
        $shipping = $order->getShippingAddress();
        if (!empty($shipping)) {
            $request = $this->_setShippingAddress($request, $shipping);
        }

        $billing = $order->getBillingAddress();
        $billing->setCustomerEmail($order->getCustomerEmail());
        if (!empty($billing)) {
            $request = $this->_setBilling($request, $billing);
            $request->setEmail($order->getCustomerEmail());
        }
        $request = $this->_prepareItemsData($request, $payment);

        return $request;
    }

    /**
     * Add order items data to request
     * @param $request
     * @param $payment
     * @return mixed
     */
    public function _prepareItemsData($request, $payment)
    {

        $order = $payment->getOrder();
        $items = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $items[] = [
                'item_id' => $item->getSku(),
                'item_description' => $item->getProduct()->getName(),
                'item_price' => $item->getPrice(),
                'item_quantity' => $item->getQtyOrdered(),
            ];
        }
        if (!empty($items)) {
            $request->setItems($items);
        }

        return $request;
    }

    /**
     * Get gateway url
     *
     * @return string
     */
    public function _getGatewayStatus()
    {
        return self::TRANSACTION_URL;
    }

    /**
     * If response is failed throw exception
     *
     * @param DataObject $response
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\State\InvalidTransitionException
     */
    public function _processErrors($response)
    {
        $body = json_decode($response, true);
        if (!in_array($body['status'], [self::RESPONSE_CODE_APPROVED, self::RESPONSE_CODE_VERIFICATION_REQUIRED])) {
            $errorMessage = $this->_buildErrorMessage($body);
            throw new LocalizedExceptionAlias($errorMessage);
        }
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
                if (!$extensionAttributes->getVaultPaymentToken()) {
                    $this->_savePaymentToken($payment, $body, $requestParams);
                }
                $payment->setTransactionId($body['transaction']['id'])->setIsTransactionClosed(true);
                try {
                    $payment->getOrder()->setState(Order::STATE_PROCESSING)->setStatus(Order::STATE_PROCESSING);
                    $payment->getOrder()->setVendoPaymentResponseStatus(self::PAYMENT_RESPONSE_STATUS_NOT_USE_IN_CRON); // Save flag for use in cron job.
                    $payment->getOrder()->save();
                } catch (\Exception $e) {
                    $this->vendoHelpers->log($e->getMessage(), LogLevel::ERROR);
                }
                break;
            case self::RESPONSE_CODE_VERIFICATION_REQUIRED:
                $message = '3DS Verification required. Redirecting to Vendo\'s verification endpoint';
                $this->vendoHelpers->log(
                    $message,
                    LogLevel::DEBUG);
                $this->vendoHelpers->addOrderCommentForAdmin($payment->getOrder(), $message);
                $this->_savePaymentToken($payment, $body, $requestParams);
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
                //$this->vendoHelpers->log('$requestParams: ' . var_export($requestParams, true), LogLevel::DEBUG); // For debug
                try {
                    $payment->getOrder()->setVendoPaymentResponseStatus(self::PAYMENT_RESPONSE_STATUS_USE_IN_CRON);
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
     * Add order id to payment request
     *
     * @param DataObject $request
     * @param Order $order
     * @return void
     */
    public function _addRequestOrderInfo($request, $order)
    {
        $id = $order->getId();

        $orderIncrementId = $order->getIncrementId();
        return $request->setExternalReferences(['transaction_reference' => $orderIncrementId]);
    }

    /**
     * Add ip/browser data to request
     *
     * @param $request
     * @return mixed
     */
    public function setRequestDetails($request)
    {
        return $request->setRequestDetails(
            [
                'ip_address' => $this->getIpAddress(),
                'browser_user_agent' => $this->getUserAgent()
            ]
        );
    }

    /**
     * Return request object with basic information for gateway request
     *
     * @return DataObject
     */
    public function _prepareGatewayData($payment, $amount)
    {
        $request = new DataObject();
        $isTest = (int)($this->getConfigData('is_test'));
        $request->setIsTest($isTest);
        $request->setMerchantId($this->getConfigData('merchant_id'));
        $request->setSiteId($this->getConfigData('site_id'));
        if ($isTest) {
            $request->setApiSecret($this->_encryptor->decrypt($this->getConfigData('api_secret_tests')));
        } else {
            $request->setApiSecret($this->_encryptor->decrypt($this->getConfigData('api_secret')));
        }

        // Add Settings 'success_url' .
        if (!empty($this->getConfigData('success_url'))) {
            $request->setSuccessUrl($this->getConfigData('success_url'));
        }

        // Set 'non_recurring' = true.
        $request->setNonRecurring(true);

        $request = $this->_prepareCardDetails($request, $payment, $amount);
        $request = $this->setRequestDetails($request);

        return $request;
    }

    /**
     * Return request object with basic information for gateway request
     *
     * @return DataObject
     */
    public function _prepareBasicGatewayData()
    {
        $request = new DataObject();

        $isTest = (int)($this->getConfigData('is_test'));
        $request->setIsTest($isTest);
        $request->setMerchantId($this->getConfigData('merchant_id'));
        $request->setSiteId($this->getConfigData('site_id'));
        if ($isTest) {
            $request->setApiSecret($this->_encryptor->decrypt($this->getConfigData('api_secret_tests')));
        } else {
            $request->setApiSecret($this->_encryptor->decrypt($this->getConfigData('api_secret')));
        }

        // Add Settings 'success_url' .
        if (!empty($this->getConfigData('success_url'))) {
            $request->setSuccessUrl($this->getConfigData('success_url'));
        }

        // Set 'non_recurring' = true.
        $request->setNonRecurring(true);

        return $request;
    }

    /**
     * Get capture amount
     *
     * @param float $amount
     * @return float
     */
    protected function _getCaptureAmount($amount)
    {
        $infoInstance = $this->getInfoInstance();
        $amountToPay = round($amount, 2);
        $authorizedAmount = round($infoInstance->getAmountAuthorized(), 2);
        return $amountToPay != $authorizedAmount ? $amountToPay : 0;
    }

    /**
     * Get customer ip address
     *
     * @return false|string
     */
    protected function getIpAddress()
    {
        $ip = '';
        try {
            $ip = $this->remoteAddress->getRemoteAddress();
        } catch (\Exception $e) {
        }
        return $ip;
    }

    /**
     * Get browser details
     *
     * @return string
     */
    protected function getUserAgent()
    {
        return $this->httpHeader->getHttpUserAgent();
    }

    /**
     * Get locale
     *
     * @return false|string
     */
    public function getCurrentLocale()
    {
        $currentLocaleCode = $this->localeResolver->getLocale();
        return $languageCode = strstr($currentLocaleCode, '_', true);
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
        if($errorMessage){
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
     */
    private function _savePaymentToken($payment, $body, $requestParams)
    {
        if (!array_key_exists('payment_details_token', $body)) {
            return;
        }
        $paymentToken = $this->paymentTokenFactory->create(PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD);
        $paymentToken->setGatewayToken($body['payment_details_token']);
        $dateExp = $requestParams['payment_details']['expiration_year'] . "-" .
            $requestParams['payment_details']['expiration_month'] . "-01 00:00";
        $paymentToken->setExpiresAt(date('Y-m-d H:i:s', strtotime($dateExp)));
        $details = json_encode([
            'last4' => substr($requestParams['payment_details']['card_number'], -4),
            'expiry_year' => $requestParams['payment_details']['expiration_year'],
            'expiry_month' => $requestParams['payment_details']['expiration_month'],
            'card_type' => $this->vendoHelpers->inferCardType($requestParams['payment_details']['card_number']),
        ]);
        $paymentToken->setTokenDetails($details);

        $extensionAttributes = $this->getExtensionAttributes($payment);
        $extensionAttributes->setVaultPaymentToken($paymentToken);
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
     * clear cookie
     */
    public function deleteCookie()
    {
        try {
            $cookieMetadata = $this->cookieMetadataFactory->createPublicCookieMetadata()
                ->setPath("/");

            $this->cookieManager->deleteCookie(self::COOKIE_NAME, $cookieMetadata);
        } catch (\Exception $e) {

        }
    }

    /**
     * Retrieve payment method title
     *
     * @return string
     * @deprecated 100.2.0
     */
    public function getTitle()
    {
        return $this->getConfigData('method_title');
    }

}
