<?php

namespace IntechSoft\CustomImport\Controller\Adminhtml\Import;

use Braintree\Exception;
use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\App\Action;
use \Magento\Framework\App\Filesystem\DirectoryList;

/*use IntechSoft\CustomImport\Controller\Adminhtml\Import;*/
class Save extends Action
{
    const CUSTOM_IMPORT_DIR = 'import/current';

    const SUCCESS_MESSAGE = 'Import finished successfully';

    /**
     * @var \Magento\Indexer\Model\Indexer\CollectionFactory
     */
    private $indexerCollectionFactory;
    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */

    protected $coreRegistry;

    /**
    * @var \Magento\MediaStorage\Model\File\UploaderFactory
    */
    protected $uploader;

    /**
     * @var \Magento\Framework\Filesystem
     */
    protected $filesystem;

    /**
     * @var \IntechSoft\CustomImport\Model\Import
     */
    protected $importModel;

    /**
     * @var \Magento\Framework\Filesystem\DirectoryList
     */
    protected $directoryList;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $_messageManager;

    /**
     * @var \IntechSoft\CustomImport\Model\Url\Rebuilt
     */
    private $_rebuiltModel;

    /**
     * Index constructor.
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\Registry $coreRegistry
     * @param \Magento\MediaStorage\Model\File\UploaderFactory $uploader
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \IntechSoft\CustomImport\Model\Import $importModel
     * @param \Magento\Framework\Filesystem\DirectoryList $directoryList
     * @param \IntechSoft\CustomImport\Model\Url\Rebuilt $rebuiltModel
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Registry $coreRegistry,
        \Magento\MediaStorage\Model\File\UploaderFactory $uploader,
        \Magento\Framework\Filesystem $filesystem,
        \IntechSoft\CustomImport\Model\Import $importModel,
        \Magento\Framework\Filesystem\DirectoryList $directoryList,
        \Magento\Indexer\Model\Indexer\CollectionFactory $indexerCollectionFactory,
        \IntechSoft\CustomImport\Model\Url\Rebuilt $rebuiltModel
    ) {
        parent::__construct($context);
        $this->uploader = $uploader;
        $this->coreRegistry = $coreRegistry;
        $this->importModel = $importModel;
        $this->directoryList = $directoryList;
        $this->indexerCollectionFactory = $indexerCollectionFactory;
        $this->_messageManager = $context->getMessageManager();
        $this->_rebuiltModel = $rebuiltModel;
    }

    /**
     * @return mixed
     */
    public function execute()
    {
        $storeManager = $this->_objectManager->get('Magento\Store\Model\StoreManagerInterface');
        $storeId = $storeManager->getStore()->getStoreId();
        ini_set('memory_limit', '2048M');
        $importSettings = array();
        $importParams = $this->getRequest()->getParam('import');
        foreach ($importParams as $name => $value) {
            if ($value != '' && $value != 0 || $name == 'select_type_attributes'){
                $importSettings[$name] = $value;
            }
        }
        /*if ($imageUploadDirectory) {
            $importSettings['import_images_file_dir'] = $imageUploadDirectory;
        }*/
        if (isset($_FILES['import']) && isset($_FILES['import']['name']) && strlen($_FILES['import']['name'])) {
            try {
                $importAllowed = true;
                $uploader = $this->uploader->create(
                    ['fileId' => 'import']
                );
                $importDir = $this->directoryList->getPath(DirectoryList::VAR_DIR) . '/' . self::CUSTOM_IMPORT_DIR ;
                $importedFileName = $this->getFileName($_FILES['import']['name']);
                $uploader->setAllowedExtensions(['csv', 'CSV']);
                $uploader->setAllowRenameFiles(true);
               //$uploader->setFilesDispersion(true);
                $uploader->setAllowCreateFolders(true);
                $result = $uploader->save(
                    $importDir , $importedFileName
                );
            } catch (\Exception $e) {
                $importAllowed = false;
                if ($e->getCode() == 0) {
                    $this->messageManager->addError($e->getMessage());
                }
            }
        }

        if ($importAllowed) {
            $this->importModel->setCsvFile($importedFileName);
            $this->importModel->process($importSettings);
            if (count($this->importModel->errors) == 0) {
                $this->_messageManager->addSuccess(__(self::SUCCESS_MESSAGE));
            } else {
                foreach ($this->importModel->errors as $error) {
                    if (is_array($error)) {
                        $error = implode(' - ', $error);
                    }
                    $this->_messageManager->addErrorMessage($error);
                }
            }
        }

        $resultMessage = $this->_rebuiltModel->rebuildProductUrlRewrites();
        $this->_messageManager->addSuccess(__($resultMessage));

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath('customimport/import/index');
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $model = $objectManager->create('\IntechSoft\CustomImport\Model\Attributes');


        $model->convertColorToSwatches($storeId);
        $model->convertSizeToSwatches($storeId);
        $this->reindex();
        return $resultRedirect;
    }

    /**
     * @param $oldName
     * @return string file name
     */
    protected function getFileName($oldName)
    {
        $oldName = str_replace ( '.csv' , '' , $oldName);
        $curDate = date('Y_m_d_H_i');
        $oldName = $oldName . '_' . $curDate . '.csv';
        return $oldName;
    }

    /**
     * Perform full reindex
     */
    private function reindex()
    {
        foreach ($this->indexerCollectionFactory->create()->getItems() as $indexer) {
            if ($indexer->getStatus() != 'valid'){
                $indexer->reindexAll();
            }
        }
    }

}
