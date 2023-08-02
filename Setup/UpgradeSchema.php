<?php

namespace Vendo\Gateway\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * Upgrades DB schema for a module
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        $orderTable = 'sales_order'; // Table name.
        if (version_compare($context->getVersion(), '1.0.0', '>=')) {
            $installer->getConnection()->addColumn(
                $installer->getTable($orderTable),
                'vendo_payment_response_status',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
                    'length' => '2',
                    'default' => 1,
                    'nullable' => false,
                    'comment' => '1 => not use job, 2 => use in cron job (vendo_gateway_checks_if_successful_verification_for_payment_cron)'
                ]
            );
            $installer->getConnection()->addColumn(
                $installer->getTable($orderTable),
                'request_object_vendo',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => 65536,
                    'default' => NULL,
                    'nullable' => true,
                    'comment' => 'Use in cron job (vendo_gateway_checks_if_successful_verification_for_payment_cron)'
                ]
            );
        }

        $installer->endSetup();
    }
}
