<?php
namespace IntechSoft\CustomImport\Cron\Import;
use \Magento\Framework\App\Filesystem\DirectoryList;

class Hour
{


    const CUSTOM_IMPORT_FOLDER = 'import/cron/hour';

    const SUCCESS_MESSAGE = 'Import finished successfully from file - ';

    const FAIL_MESSAGE = 'Import fail from file - ';
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @var \Magento\MediaStorage\Model\File\UploaderFactory
     */
    protected $_uploader;

    /**
     * @var \Magento\Framework\Filesystem
     */
    protected $_filesystem;

    /**
     * @var \IntechSoft\CustomImport\Model\Import
     */
    protected $_importModel;

    /**
     * @var \Magento\Framework\Filesystem\DirectoryList
     */
    protected $_directoryList;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $_date;

    /**
     * Import constructor.
     * @param \Magento\MediaStorage\Model\File\UploaderFactory $uploader
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \IntechSoft\CustomImport\Model\Import $importModel
     * @param \Magento\Framework\Filesystem\DirectoryList $directoryList
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $date
     */
    public function __construct(
        \Magento\MediaStorage\Model\File\UploaderFactory $uploader,
        \Magento\Framework\Filesystem $filesystem,
        \IntechSoft\CustomImport\Model\Import $importModel,
        \Magento\Framework\Filesystem\DirectoryList $directoryList,
        \Magento\Framework\Stdlib\DateTime\DateTime $date

    ) {
        $this->_uploader = $uploader;
        $this->_filesystem = $filesystem;
        $this->_importModel = $importModel;
        $this->_directoryList = $directoryList;
        $this->_date = $date;
        //$this->_logger = $logger;

        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/test.log');
        $this->_logger = new \Zend\Log\Logger();
        $this->_logger->addWriter($writer);
    }

    /**
     * Method executed when cron runs in server
     */
    public function execute()
    {

        $this->_logger->info('hourly cron started at - ' . $this->_date->gmtDate('Y-m-d H:i:s'));
        ini_set('memory_limit', '2048M');

        $importDir = $this->_directoryList->getPath(DirectoryList::VAR_DIR) . '/' . self::CUSTOM_IMPORT_FOLDER ;

        $this->_logger->info('$importDir - '.$importDir);
//        $this->_logger->info(is_dir($importDir));

        if(!is_dir($importDir)) {
            mkdir($importDir, 0775);
        }

        $fileList = scandir($importDir);

        $i = 0;
        foreach ($fileList as $file) {

            if ($file == '.' || $file == '..'){
                continue;
            }
            $i++;
           // $this->_logger->info(print_r($file, true));
            $importedFileName = $importDir . '/' . $file;

            $this->_logger->info('$importedFileName - '.$importedFileName);

            $this->_importModel->setCsvFile($importedFileName, true);
            $this->_importModel->process();
            if (count($this->_importModel->errors) == 0) {
                $this->_logger->info(self::SUCCESS_MESSAGE . $file);
                unlink($importDir. '/' .$file);
            } else {
                foreach ($this->_importModel->errors as $error) {
                    if (is_array($error)) {
                        $error = implode(' - ', $error);
                    }
                    $this->_logger->info( $error);
                }

            }

            if($i <= 1){
                break;
            }
        }
        $this->_logger->info('hourly cron finished at - ' . $this->_date->gmtDate('Y-m-d H:i:s'));

    }
}