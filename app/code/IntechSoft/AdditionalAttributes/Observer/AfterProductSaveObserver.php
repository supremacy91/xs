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

    const MAXTIMEVALUE = 2140000000;
    const ATTRIBUTECODE = 'attribute_for_sale';
    const ENTITYTYPE = 'catalog_product';
    const FORSALEOPTION = 'for_sale';

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

            //get is_for_sale Option id

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

            $attributeInfo = $objectManager->get(\Magento\Eav\Model\Entity\Attribute::class)
                ->loadByCode(self::ENTITYTYPE, self::ATTRIBUTECODE);

            $attributeId = $attributeInfo->getAttributeId();
            $attributeOptionAll = $objectManager->get(\Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection::class)
                ->setPositionOrder('asc')
                ->setAttributeFilter($attributeId)
                ->setStoreFilter()
                ->load();
            $isForSaleOptionId = '';
            foreach ($attributeOptionAll as $attributeOption){
                $optionLabelValue = $attributeOption->getData('default_value');
                if($optionLabelValue == self::FORSALEOPTION){
                    $isForSaleOptionId = $attributeOption->getId();
                    break;
                }
            }


            if($productSpecialPriceFinishDate == null && $productSpecialPriceStartDate == null){
                $observer->getProduct()->setData('sorting_new_sale', self::MAXTIMEVALUE);
                $observer->getProduct()->setData(self::ATTRIBUTECODE, $isForSaleOptionId);
            } else if($productSpecialPriceFinishDate == null && $currentTime>$startTime){
                $observer->getProduct()->setData('sorting_new_sale', self::MAXTIMEVALUE);
                $observer->getProduct()->setData(self::ATTRIBUTECODE, $isForSaleOptionId);
            } else if($productSpecialPriceStartDate == null && $currentTime<$finishTime){
                $observer->getProduct()->setData('sorting_new_sale', self::MAXTIMEVALUE);
                $observer->getProduct()->setData(self::ATTRIBUTECODE, $isForSaleOptionId);
            } else if($finishTime>$currentTime && $currentTime>$startTime){
                $observer->getProduct()->setData('sorting_new_sale', self::MAXTIMEVALUE);
                $observer->getProduct()->setData(self::ATTRIBUTECODE, $isForSaleOptionId);
            } else {
                $createDateParam = self::MAXTIMEVALUE - $this->dateToSeconds($observerProduct->getData('created_at'));
                $observer->getProduct()->setData('sorting_new_sale', $createDateParam);
                $observer->getProduct()->setData(self::ATTRIBUTECODE, '');
            }

        } else {
            $createDateParam = self::MAXTIMEVALUE - $this->dateToSeconds($observerProduct->getData('created_at'));
            $observer->getProduct()->setData('sorting_new_sale', $createDateParam);
            $observer->getProduct()->setData(self::ATTRIBUTECODE, '');
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