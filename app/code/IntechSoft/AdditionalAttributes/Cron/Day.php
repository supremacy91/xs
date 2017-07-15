<?php
namespace IntechSoft\AdditionalAttributes\Cron;

/**
 * Class Data
 *
 * @package     IntechSoft\AdditionalAttributes\Cron
 * @author
 * @copyright
 * @version     1.0.1
 */

class Data
{
    const XML_PATH_REINDEX_TYPE = 'intechsoft/basic/enabled';
    const MAXTIMEVALUE = 2140000000;
    protected $_scopeConfig;
    protected $_logger;

    public function __construct(\Psr\Log\LoggerInterface $logger,
       \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->_logger = $logger;
        $this->_scopeConfig = $scopeConfig;
    }

    public function execute() {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;

        $checkTypeReindex = $this->_scopeConfig->getValue(self::XML_PATH_REINDEX_TYPE, $storeScope);

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        if($checkTypeReindex == 1){
            $productCollection = $objectManager->create('Magento\Catalog\Model\ResourceModel\Product\Collection');
        } else {
            /** @var /Magento\Catalog\Model\ResourceModel\Product\Collection $productCollection */
            $productCollection = $objectManager->create('Magento\Catalog\Model\ResourceModel\Product\Collection');
            $todayDate = date('Y-m-d');
            $productCollection->addAttributeToFilter(
                [
                    'special_from_date' => [
                        ['like' => $todayDate.'%'],
                        'attribute' => 'special_from_date'
                    ],
                    'special_to_date' => [
                        'like' => $todayDate.'%',
                        'attribute' => 'special_to_date'
                    ]
                ], null, 'left'
            );
        }

        $products = $productCollection->load();

        foreach($products as $product){
            $productId = $product->getId();
            $productForSave = '';
            $productForSave = $objectManager->get('\Magento\Catalog\Model\Product')
                ->load($productId);
            $productSpecialPrice = $productForSave->getSpecialPrice();
            if($productSpecialPrice > 0){
                if($productForSave->getData('special_to_date')!=null){
                    $productSpecialPriceFinishDate = $productForSave->getData('special_to_date');
                } else {
                    $productSpecialPriceFinishDate = null;
                }
                if($productForSave->getData('special_from_date')!=null){
                    $productSpecialPriceStartDate = $productForSave->getData('special_from_date');
                } else {
                    $productSpecialPriceStartDate = null;
                }
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
                $paramForSave = self::MAXTIMEVALUE;
                if($productSpecialPriceFinishDate == null && $productSpecialPriceStartDate == null){
                    $paramForSave = self::MAXTIMEVALUE;
                } else if($productSpecialPriceFinishDate == null && $currentTime>$startTime){
                    $paramForSave = self::MAXTIMEVALUE;
                } else if($productSpecialPriceStartDate == null && $currentTime<$finishTime){
                    $paramForSave = self::MAXTIMEVALUE;
                } else if($finishTime>$currentTime && $currentTime>$startTime){
                    $paramForSave = self::MAXTIMEVALUE;
                } else {
                    $paramForSave = self::MAXTIMEVALUE-$this->dateToSeconds($productForSave->getData('created_at'));
                }

            } else {
                $paramForSave = self::MAXTIMEVALUE-$this->dateToSeconds($productForSave->getData('created_at'));
            }
            echo $productId.'_';
            $productForSaveOne = '';
            $productForSaveOne = $objectManager->get('\Magento\Catalog\Model\Product')->load($productId);
            $productForSaveOne->setData('sorting_new_sale', $paramForSave);
            $productForSaveOne->getResource()->saveAttribute($productForSaveOne, 'sorting_new_sale');
        }

    }

    protected function dateToSeconds($inputDate){
        $tempArray = explode(' ', $inputDate);
        $tempDateArray = explode('-', $tempArray[0]);
        $tempTimeArray = explode(':', $tempArray[1]);
        $timeInSeconds = mkTime($tempTimeArray[0], $tempTimeArray[1], $tempTimeArray[2], $tempDateArray[1], $tempDateArray[2], $tempDateArray[0]);
        return $timeInSeconds;
    }
}