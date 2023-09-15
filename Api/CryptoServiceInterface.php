<?php

namespace Vendo\Gateway\Api;

interface CryptoServiceInterface
{
    /**
     * @return string
     */
    public function getVerificationUrl(): string;
}
