<?php

namespace IntechSoft\CustomImport\Controller\Adminhtml\Import;

use Braintree\Exception;
use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\App\Action;
use \Magento\Framework\App\Filesystem\DirectoryList;

class Rebuild extends Action
{

    /**
     * @var \IntechSoft\CustomImport\Model\Url\Rebuilt
     */
    private $_rebuiltModel;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $_messageManager;

    /**
     * Index constructor.
     * @param \IntechSoft\CustomImport\Model\Url\Rebuilt $rebuiltModel
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     */
    public function __construct(
        \IntechSoft\CustomImport\Model\Url\Rebuilt $rebuiltModel,
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        parent::__construct($context);
        $this->_rebuiltModel = $rebuiltModel;
        $this->_messageManager = $messageManager;
    }

    /**
     * @return void
     */
    public function execute()
    {
        $resultMessage = $this->_rebuiltModel->rebuildProductUrlRewrites();

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath('customimport/import/index');
        $this->_messageManager->addSuccess(__($resultMessage));

        return $resultRedirect;
    }
}
