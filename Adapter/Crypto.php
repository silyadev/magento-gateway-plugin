<?php

namespace Vendo\Gateway\Adapter;

class Crypto
{
    private $basicAdapter;

    public function __construct(BasicVendoAdapter $basicAdapter)
    {
        $this->basicAdapter = $basicAdapter;
    }

    public function authorize(array $request): array
    {
        return $this->basicAdapter->authorize($request);
    }

    public function capture(array $request): array
    {
        return $this->basicAdapter->capture($request);
    }

    public function refund(array $request): array
    {
        return $this->basicAdapter->refund($request);
    }
}
