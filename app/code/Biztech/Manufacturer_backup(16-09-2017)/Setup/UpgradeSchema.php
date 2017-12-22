<?php

namespace Biztech\Manufacturer\Setup;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * @codeCoverageIgnore
 */
class UpgradeSchema implements UpgradeSchemaInterface {
    /**
     * {@inheritdoc}
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context){
        $setup->startSetup();
        
        if (version_compare($context->getVersion(), '1.0.1') < 0) {            
            // initial version
        }

        if (version_compare($context->getVersion(), '1.0.2') < 0) {
           //Fixed Compilation Issues
        }

        $setup->endSetup();

    }
}
