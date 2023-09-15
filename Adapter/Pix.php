<?php

namespace Vendo\Gateway\Adapter;

class Pix
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
}
