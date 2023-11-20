<?php

declare(strict_types=1);

namespace Vendo\Gateway\Gateway\Command;

use Magento\Payment\Gateway\CommandInterface;

/**
 * Empty command to stub unnecessary commands, which are required with gateway
 */
class MockCommand implements CommandInterface
{

    /**
     * @inheritDoc
     */
    public function execute(array $commandSubject)
    {
        // Nothing here, just stub
    }
}
