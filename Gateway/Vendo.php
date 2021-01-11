<?php
namespace Vendo\Gateway\Gateway;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Validator\Exception;
use Vendo\Gateway\Model\VendoHelpers;

/**
 * Class Vendo
 * @package Vendo\Gateway\Gateway
 */
class Vendo
{

    /**
     * @var Curl
     */
    protected $_curl;

    /**
     * @var VendoHelpers
     */
    protected $vendoHelpers;

    /**
     * Vendo constructor.
     * @param VendoHelpers $vendoHelpers
     * @param Curl $curl
     */
    public function __construct(
        VendoHelpers $vendoHelpers,
        Curl $curl
    )
    {
        $this->vendoHelpers = $vendoHelpers;
        $this->_curl = $curl;
    }

    /**
     * @throws Exception
     */
    public function initialiseCurl()
    {
        if (!function_exists('curl_init')) {
            $this->debugData(
                ['request' => "Vendo Gateway", 'exception' => "Curl not enabled. Please enable curl"]
            );
            throw new Exception(__('Payment capturing error.'));
        }
    }

    /**
     * @param $request
     * @param $gatewayurl
     * @return string
     * @throws Exception
     */
    public function postRequest($request, $gatewayurl)
    {

        $this->initialiseCurl();

        $params = $request->toArray();
        $data = json_encode($params);
        $response = $this->_processPostRequest($data, $gatewayurl);

        return $response;
    }

    /**
     * @param $gatewaydata
     * @param $gatewayurl
     * @return string
     */
    public function _processPostRequest($gatewaydata, $gatewayurl)
    {

        $headers = ["Content-Type" => "application/json", "charset" => "utf-8"];
        $this->_curl->setHeaders($headers);
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->setOption(CURLOPT_SSL_VERIFYPEER, false);
        $this->_curl->setOption(CURLOPT_SSL_VERIFYHOST, 0);
        $this->_curl->setOption(CURLOPT_CONNECTTIMEOUT, 0);
        $this->_curl->setOption(CURLOPT_TIMEOUT, 300);
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->post($gatewayurl, $gatewaydata);

        return $response = $this->_curl->getBody();
    }

}
