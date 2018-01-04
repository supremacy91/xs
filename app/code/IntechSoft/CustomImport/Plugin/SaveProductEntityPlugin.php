<?php
namespace IntechSoft\CustomImport\Plugin;

class SaveProductEntityPlugin extends \Magento\CatalogImportExport\Model\Import\Product{

        public function afterSaveProductEntity(\Magento\CatalogImportExport\Model\Import\Product $subject, $result){
            $om = \Magento\Framework\App\ObjectManager::getInstance();
            $registryImportFlag = $om->get('\Magento\Framework\Registry');
            if ($result instanceof \Magento\CatalogImportExport\Model\Import\Product) {
                $registryImportFlag->register('importSuccessFlag', 1, true);
            }
        return $result;

    }


}

?>