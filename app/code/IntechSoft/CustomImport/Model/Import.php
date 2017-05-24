<?php

namespace IntechSoft\CustomImport\Model;

use Braintree\Exception;
use \Magento\Framework\App\Filesystem\DirectoryList;
use \Magento\ImportExport\Model\Import\Adapter as ImportAdapter;

class Import extends \Magento\Catalog\Model\AbstractModel
{


    const MSG_SUCCESS               = 'Successfully';
    const MSG_FAILED                = 'Import fail';
    const MSG_PREPARE_DATA_FAILED   = 'Prepare data was fail';
    const MSG_VALIDATION_FAILED     = 'Data validation is failed. Please fix errors and try again';
    const MSG_NO_DATA_FOUND         = 'No data found. Please try again latter';

    const MSG_MAX_ERRORS            = 80000;

    const MSG_VALIDATION_STATUS     = 'Checked rows: %d; Checked entities: %d; Invalid rows: %d; Total errors: %d';
    const MSG_IMPORT_FINISHED       = 'Import finished';
    const MSG_IMPORT_TERMINATED     = 'Import terminated';

    const LOG_FILE                  = 'Custom_Import.log';

    const ERROR                     = 'ERROR: %s';

    const PREPARE_DATA_PROCESS_ERROR = 'Prepare data for import error ';
    const PREPARED_CSV_MISSED = 'prepared csv file missed';

    protected $_importCsv;

    protected $_preparedCsvFile;

    /**
     * @var \IntechSoft\CustomImport\Helper\Import
     */
    protected $_importHelper;

    /**
     * @var \Magento\Framework\App\Filesystem\DirectoryList
     */
    protected $_directoryList;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \Magento\Framework\Filesystem
     */
    protected $filesystem;

    /**
     * @var \Magento\Framework\File\Csv
     */
    protected $csvProcessor;

    /**
     * @var \IntechSoft\CustomImport\Model\Attributes
     */
    protected $attributesModel;

    protected $_registry;

    public $importSettings;

    public $errors = array();

    public $successMessages = array();


    /**
     * @var \Zend\Log\Writer\Stream
     */
    protected $_exportLogger;

    const CUSTOM_IMPORT_FOLDER = 'import/current';

    /**
     * Import constructor.
     * @param \IntechSoft\CustomImport\Helper\Import $importHelper
     * @param \IntechSoft\CustomImport\Model\Attributes $attributesModel
     * @param \Magento\Framework\App\Filesystem\DirectoryList $directoryList
     * @param \Magento\Framework\ObjectManagerInterface $objectmanager
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\File\Csv $csvProcessor
     */

    public function __construct(
        \IntechSoft\CustomImport\Helper\Import $importHelper,
        \IntechSoft\CustomImport\Model\Attributes $attributesModel,
        \Magento\Framework\App\Filesystem\DirectoryList $directoryList,
        \Magento\Framework\ObjectManagerInterface $objectmanager,
        \Magento\Framework\Filesystem $filesystem,

        \Magento\Framework\File\Csv $csvProcessor,
        \Magento\Framework\Registry $registry
    )
    {
        $this->filesystem = $filesystem;
        $this->attributesModel = $attributesModel;
        $this->_importHelper = $importHelper;
        $this->_directoryList = $directoryList;
        $this->_registry = $registry;
        $this->csvProcessor = $csvProcessor;
        $this->objectManager = $objectmanager;

        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/' . $this::LOG_FILE);
        $this->_exportLogger = new \Zend\Log\Logger();
        $this->_exportLogger->addWriter($writer);
    }

    /**
     * set uploaded csv file name
     * @param $filename
     * @param $cron
     */
    public function setCsvFile($filename, $cron = false)
    {
        if ($cron) {
            $this->_importCsv = $filename;
        } else {
            $importFolderPath = $this->_directoryList->getPath('var') . '/' . self::CUSTOM_IMPORT_FOLDER;
            if (!is_dir($importFolderPath)) {
                mkdir($importFolderPath, 0775);
            }
            $this->_importCsv = $importFolderPath . '/' . $filename;
        }
    }

    /**
     * @return csv file name
     */
    public function getCsvFile()
    {
        return $this->_importCsv;
    }

    /**
     * @param $importSettings
     * @return $results array
     */
    public function process($importSettings = false)
    {
        $result = array();
        if (count($importSettings) && $importSettings){
            $this->importSettings = $importSettings;
        }
        if ($this->prepareData()){
            $this->attributesModel->setAttributeSettings($this->importSettings);
            $this->attributesModel->checkAddAttributes($this->_preparedCsvFile);
            $this->execute();
        } else {
            $this->errors[] = self::MSG_PREPARE_DATA_FAILED;
        }
        $result['error_message'] = $this->errors;


        return $result;
    }

    /**
     * execute import function
     */
    public function execute()
    {
        //$this->_importCsv = '';

        if (!empty($this->_preparedCsvFile)) {
            $this->callImport();
        } else {
            $this->errors[] = self::PREPARED_CSV_MISSED;
        }
    }

    /**
     * import process function
     * @return $this
     */
    protected function callImport()
    {
        if (empty($this->_preparedCsvFile)) {
            return $this;
        }

        $data = [
            'entity'                            => 'catalog_product',
            'behavior'                          => 'append',
            'validation_strategy'               => 'validation-stop-on-errors',
            'allowed_error_count'               => 10,
            '_import_field_separator'           => ',',
            '_import_multiple_value_separator'   => ',',
            'import_images_file_dir'            => 'pub/media/import'
        ];

        if ($this->importSettings) {
            $data = $this->prepareImportSettings($data);
        }

        /** @var $importModel \Magento\ImportExport\Model\Import */
        $importModel = $this->objectManager->create('Magento\ImportExport\Model\Import')->setData($data);

        /** @var $source \Magento\ImportExport\Model\Import\Source\Csv */
        //$this->_preparedCsvFile = '/var/www/html/magento2-test/var/import/calexis-stockfile-newwithheader-test.csv';
        $source = ImportAdapter::findAdapterFor(
            $this->_preparedCsvFile,
            $this->objectManager->create('Magento\Framework\Filesystem')
                ->getDirectoryWrite(DirectoryList::ROOT),
            $data[$importModel::FIELD_FIELD_SEPARATOR]
        );

        $validationResult = $importModel->validateSource($source);
        $errorAggregator = $importModel->getErrorAggregator();
        $this->successMessages;

        if (!$importModel->getProcessedRowsCount()) {
            if (!$errorAggregator->getErrorsCount()) {
                $this->errors[] = [self::MSG_NO_DATA_FOUND];
                $this->stopImport();
            } else {
                $this->errors[] = [self::MSG_FAILED];
                foreach ($errorAggregator->getAllErrors() as $error) {
                    $this->errors[] = sprintf(self::ERROR, $error->getErrorMessage());
                }
            }
        } else {
            if (!$validationResult) {
                $this->errors[] = [self::MSG_VALIDATION_FAILED];
                foreach ($errorAggregator->getAllErrors() as $error) {
                    $this->errors[] = sprintf(self::ERROR, $error->getErrorMessage());
                }
                ///$messages = [self::MSG_VALIDATION_FAILED];
            } else {
                if ($importModel->isImportAllowed()) {
                    $this->successMessages = [self::MSG_SUCCESS];
                } else {
                    $this->errors[] = [self::MSG_VALIDATION_FAILED];
                }
            }
        }


        /**
         * Starting Import Process
         */
        if ($importModel->getProcessedRowsCount() && $validationResult && $importModel->isImportAllowed()) {

            $importResult = $importModel->importSource();
           if (!$importResult) {
               $errorAggregator = $importModel->getErrorAggregator();
           } else {

           }



            if ($errorAggregator->hasToBeTerminated()) {
                $this->errors[] = [
                    self::MSG_MAX_ERRORS,
                    '',
                    self::MSG_IMPORT_TERMINATED
                ];
                foreach ($errorAggregator->getAllErrors() as $error) {
                    $this->errors[] = sprintf(self::ERROR, $error->getErrorMessage());
                }

            } else {
                $importModel->invalidateIndex();
                $this->successMessages = [
                    self::MSG_SUCCESS,
                    '',
                    self::MSG_IMPORT_FINISHED
                ];
            }
        } else {
            $this->errors[] = [
                '',
                self::MSG_IMPORT_TERMINATED
            ];

            /*if ($this->_stopOnError) {
                $this->registry->register('import_terminated', true);
                $this->stopImport();
            }*/
        }


        return $this;
    }

    /**
     * @param $data
     * @return prepared import settings data
     */
    protected function prepareImportSettings($data)
    {
        foreach ($this->importSettings as $index => $value) {
            if (isset($data[$index])){
                $data[$index] = $value;
            }
        }
        return $data;
    }

    /*protected function stopImport()
    {
        if (!($this->registry->registry('finish_data'))) {
            $this->registry->register('finish_data', true);
        }
    }*/

    /**
     * prepare dataa for import and save it to new csv file
     * @return boolean
     */
    protected function prepareData()
    {
        $csvFile = $this->getCsvFile();

        $dataBefore = $this->csvProcessor->getData($csvFile);

        if (isset($this->importSettings['root_category']) && $this->importSettings['root_category'] != '') {
            $this->_importHelper->rootCategory = $this->importSettings['root_category'];
        }

        if (isset($this->importSettings['attribute_set']) && $this->importSettings['attribute_set'] != '') {
            $this->_importHelper->setAttributeSet($this->importSettings['attribute_set']) ;
        }


        if ($dataAfter = $this->_importHelper->prepareData($dataBefore)){
            $dataAfterConfigurable = $this->_importHelper->prepareDataConfigurable($dataBefore);
            $this->_preparedCsvFile = $this->_importCsv;
            array_shift($dataAfterConfigurable);
            $dataAfter = array_merge($dataAfter, $dataAfterConfigurable);
            $this->csvProcessor->saveData($this->_preparedCsvFile, $dataAfter);
        } else {
            $this->_errorMessage = self::PREPARE_DATA_PROCESS_ERROR ;
            return false;
        }
        return true;
    }

}