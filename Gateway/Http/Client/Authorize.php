<?php

namespace Vendo\Gateway\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Vendo\Gateway\Adapter\Pix;

class Authorize implements ClientInterface
{
    private $pix;

    public function __construct(Pix $pix)
    {
        $this->pix = $pix;
    }

    /**
     * @inheritDoc
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $params = $transferObject->getBody();
        return $this->pix->authorize($params);
    }
}
