<?php

namespace Vendo\Gateway\Api;

interface PixServiceInterface
{
    /**
     * @return string
     */
    public function getVerificationUrl(): string;
}
