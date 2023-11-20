<?php

namespace Vendo\Gateway\Adapter;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Vendo\Gateway\Gateway\Vendo;

class BasicVendoAdapter
{
    public const PAYMENT_REQUEST_URI = 'https://secure.vend-o.com/api/gateway/payment';

    public const PAYMENT_CAPTURE_URI = 'https://secure.vend-o.com/api/gateway/capture';

    public const PAYMENT_REFUND_URI = 'https://secure.vend-o.com/api/gateway/refund';

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
        $curlRequest->setHeaders(
            $this->getDefaultHeaders()
        );
        $curlRequest->post(self::PAYMENT_REQUEST_URI, $this->jsonSerializer->serialize($request));

        return $this->jsonSerializer->unserialize($curlRequest->getBody());
    }

    public function capture(array $request): array
    {
        $curlRequest = $this->curlClient;
        $curlRequest->setHeaders(
            $this->getDefaultHeaders()
        );
        $curlRequest->post(self::PAYMENT_CAPTURE_URI, $this->jsonSerializer->serialize($request));

        return $this->jsonSerializer->unserialize($curlRequest->getBody());
    }

    public function refund(array $request): array
    {
        $curlRequest = $this->curlClient;
        $curlRequest->setHeaders(
            $this->getDefaultHeaders()
        );
        $curlRequest->post(self::PAYMENT_REFUND_URI, $this->jsonSerializer->serialize($request));

        return $this->jsonSerializer->unserialize($curlRequest->getBody());
    }

    private function getDefaultHeaders(): array
    {
        return [
            'content-type' => 'application/json',
            'accept' => 'application/json',
            "X-VENDOGWAPI_PLUGIN" => Vendo::VENDO_MODULE_VERSION
        ];
    }
}
