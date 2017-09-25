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

class Day
{
    const XML_PATH_REINDEX_TYPE = 'intechsoft/basic/enabled';
    const MAXTIMEVALUE = 2140000000;
    const ATTRIBUTECODE = 'attribute_for_sale';
    const ATTRIBUTECODE_SALE_VALUE = 'for_sale';
    const ATTRIBUTECODE_NOTSALE_VALUE = 'not_for_sale';
    const ENTITYTYPE = 'catalog_product';
    const FORSALEOPTION = 'for_sale';
    const XML_PATH_SALE_CATEGORY_ID = 'intechsoft/basic/salecategoryid';
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
                $paramForSaveIsForSale = 1;


                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

                /*$attributeInfo = $objectManager->get(\Magento\Eav\Model\Entity\Attribute::class)
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
                }*/

                if($productSpecialPriceFinishDate == null && $productSpecialPriceStartDate == null){
                    $paramForSave = self::MAXTIMEVALUE;
                    //$paramForSaveIsForSale = $isForSaleOptionId;
                } else if($productSpecialPriceFinishDate == null && $currentTime>$startTime){
                    $paramForSave = self::MAXTIMEVALUE;
                    //$paramForSaveIsForSale = $isForSaleOptionId;
                } else if($productSpecialPriceStartDate == null && $currentTime<$finishTime){
                    $paramForSave = self::MAXTIMEVALUE;
                    //$paramForSaveIsForSale = $isForSaleOptionId;
                } else if($finishTime>$currentTime && $currentTime>$startTime){
                    $paramForSave = self::MAXTIMEVALUE;
                    //$paramForSaveIsForSale = $isForSaleOptionId;
                } else {
                    $paramForSave = self::MAXTIMEVALUE-$this->dateToSeconds($productForSave->getData('created_at'));
                    $paramForSaveIsForSale = '';
                }

                $productForSaveCategories = '';
                $productForSaveCategories = $objectManager->get('\Magento\Catalog\Model\Product')->load($productId);
                $saleCategoryId = $this->_scopeConfig->getValue(self::XML_PATH_SALE_CATEGORY_ID, $storeScope);
                $arrayOfCategories = $productForSaveCategories->getCategoryIds();
                if(!in_array($saleCategoryId, $arrayOfCategories)){
                    $arrayOfCategories[count($arrayOfCategories)]=strval($saleCategoryId);
                }
                $productForSaveCategories->setCategoryIds($arrayOfCategories)->save();

            } else {
                $productForSaveCategories = '';
                $productForSaveCategories = $objectManager->get('\Magento\Catalog\Model\Product')->load($productId);
                $saleCategoryId = $this->_scopeConfig->getValue(self::XML_PATH_SALE_CATEGORY_ID, $storeScope);
                $arrayOfCategories = $productForSaveCategories->getCategoryIds();
                if(in_array($saleCategoryId, $arrayOfCategories)){
                    if(($key = array_search($saleCategoryId, $arrayOfCategories)) !== false) {
                        unset($arrayOfCategories[$key]);
                    }
                }
                $productForSaveCategories->setCategoryIds($arrayOfCategories)->save();
                $paramForSave = self::MAXTIMEVALUE-$this->dateToSeconds($productForSave->getData('created_at'));

                //$paramForSaveIsForSale = '';
            }
            $productForSaveOne = '';
            $productForSaveOne = $objectManager->get('\Magento\Catalog\Model\Product')->load($productId);
            $productForSaveOne->setData('sorting_new_sale', $paramForSave);
            $productForSaveOne->getResource()->saveAttribute($productForSaveOne, 'sorting_new_sale');


            $discountData = $productForSave->getData('discount');
            $saleValue = '';
            if($discountData == 'New Collection'){
                $saleValue = self::ATTRIBUTECODE_NOTSALE_VALUE;
            } else if($discountData == 'Sale'){
                $saleValue = self::ATTRIBUTECODE_SALE_VALUE;
            } else {
                $saleValue = self::ATTRIBUTECODE_NOTSALE_VALUE;
            }
            $attributeInfo = $objectManager->get(\Magento\Eav\Model\Entity\Attribute::class)
                ->loadByCode(self::ENTITYTYPE, self::ATTRIBUTECODE);

            $attributeId = $attributeInfo->getAttributeId();
            $attributeOptionAll = $objectManager->get(\Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection::class)
                ->setPositionOrder('asc')
                ->setAttributeFilter($attributeId)
                ->load();

            $isForSaleOptionId = '';
            foreach ($attributeOptionAll as $attributeOption){
                $optionLabelValue = $attributeOption->getData('default_value');
                if($optionLabelValue == $saleValue){
                    $isForSaleOptionId = $attributeOption->getId();
                    break;
                }
            }

            $productForSaveTwo = '';
            $productForSaveTwo = $objectManager->get('\Magento\Catalog\Model\Product')->load($productId);
            $productForSaveTwo->setData(self::ATTRIBUTECODE, $isForSaleOptionId);
            $productForSaveTwo->getResource()->saveAttribute($productForSaveTwo, self::ATTRIBUTECODE);
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