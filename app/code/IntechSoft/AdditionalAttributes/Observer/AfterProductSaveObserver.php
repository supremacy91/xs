<?php
namespace IntechSoft\AdditionalAttributes\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Catalog\Model\Product as ModelProduct;

/**
 * Class AfterProductSaveObserver
 * @package
 * @author
 * @copyright
 * @version     1.0.1
 */

class AfterProductSaveObserver implements ObserverInterface
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @var $_jsHelper \Magento\Backend\Helper\Js
     */
    protected $_jsHelper;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    //protected $messageManager;
    protected $_request;

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $productFactory;

    /**
     * CmspagesDeleteObserver constructor.
     * @param \Psr\Log\LoggerInterface $loggerInterface
     * @param \Magento\Backend\Helper\Js $jsHelper
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param \Magento\Catalog\Model\ProductFactory $productFactory
     */

    public function __construct(
        \Psr\Log\LoggerInterface $loggerInterface,
        \Magento\Backend\Helper\Js $jsHelper,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Catalog\Model\ProductFactory $productFactory
    ) {
        $this->_logger = $loggerInterface;
        $this->_jsHelper = $jsHelper;
        //$this->messageManager = $messageManager;
        $this->_request = $request;
        $this->productFactory = $productFactory;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $observerProduct = $observer->getProduct();
        $productSpecialPrice = $observerProduct->getSpecialPrice();
        if($productSpecialPrice > 0){
            $productSpecialPriceFinishDate = $observerProduct->getData('special_to_date');
            $productSpecialPriceStartDate = $observerProduct->getData('special_from_date');
            // check special price finish date
            $currentTime = time();
            $finishTime = 0;
            $startTime = 0;
            if($productSpecialPriceFinishDate!=null){
                $finishTime = $this->dateToSeconds($productSpecialPriceFinishDate);
            }
            if($productSpecialPriceStartDate!=null){
                $startTime = $this->dateToSeconds($productSpecialPriceStartDate);
            }

            if($productSpecialPriceFinishDate == null && $productSpecialPriceStartDate == null){
                $observer->getProduct()->setData('sorting_new_sale', 2140000000);
            } else if($productSpecialPriceFinishDate == null && $currentTime>$startTime){
                $observer->getProduct()->setData('sorting_new_sale', 2140000000);
            } else if($productSpecialPriceStartDate == null && $currentTime<$finishTime){
                $observer->getProduct()->setData('sorting_new_sale', 2140000000);
            } else if($finishTime>$currentTime && $currentTime>$startTime){
                $observer->getProduct()->setData('sorting_new_sale', 2140000000);
            } else {
                $createDateParam = 2140000000 - $this->dateToSeconds($observerProduct->getData('created_at'));
                $observer->getProduct()->setData('sorting_new_sale', $createDateParam);
            }

        } else {
            $createDateParam = 2140000000 - $this->dateToSeconds($observerProduct->getData('created_at'));
            $observer->getProduct()->setData('sorting_new_sale', $createDateParam);
        }
    }

    private function dateToSeconds($inputDate){
        $tempArray = explode(' ', $inputDate);
        $tempDateArray = explode('-', $tempArray[0]);
        $tempTimeArray = explode(':', $tempArray[1]);
        $timeInSeconds = mkTime($tempTimeArray[0], $tempTimeArray[1], $tempTimeArray[2], $tempDateArray[1], $tempDateArray[2], $tempDateArray[0]);
        return $timeInSeconds;
    }

}