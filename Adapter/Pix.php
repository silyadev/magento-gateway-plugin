<?php

namespace Vendo\Gateway\Adapter;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;

class Pix
{
    public const PAYMENT_REQUEST_URI = 'https://secure.vend-o.com/api/gateway/payment';

    private $curlClient;

    private $jsonSerializer;

    public function __construct(Curl $curlClient, Json $jsonSerializer)
    {
        $this->curlClient = $curlClient;
        $this->jsonSerializer = $jsonSerializer;
    }
    public function authorize(array $request): array
    {
        $curlRequest = $this->curlClient;
        $curlRequest->setHeaders(['content-type' => 'application/json', 'accept' => 'application/json']);
        $curlRequest->post(self::PAYMENT_REQUEST_URI, $this->jsonSerializer->serialize($request));

        return $this->jsonSerializer->unserialize($curlRequest->getBody());
    }
}
