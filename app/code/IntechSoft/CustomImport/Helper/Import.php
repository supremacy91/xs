<?php

namespace IntechSoft\CustomImport\Helper;

use Magento\Framework\App\Helper\Context;

class Import extends \Magento\Framework\App\Helper\AbstractHelper
{
    const IMAGE_URL_TO_CUT = 'https://calexis.nl/userfiles/files';

    public $rootCategory = 'Default Category';

    protected $_registry;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    public $allowedImagesExtensions = array (
        'jpg',
        'png',
        'gif'
    );

    protected $standardAttributes = array(
        'attribute_set_code' => 'Default',
        'product_type' => 'simple',
        'product_websites' => 'base',
        'is_in_stock' => 1,
        'manage_stock' => 0,
        'website_id' => 0,
        'product_online' => 1,
        'base_image' => '',
        'small_image' => '',
        'thumbnail_image' => '',
        'configurable_variations' => '',
        'default_category' => '',
        'visibility' => 'niet individueel zichtbaar'
    );

    public $attributesMapping = array(
        'SKU' => 'sku',
        'Short Description' => 'short_description',
        'Description' => 'description',
        'Category' => 'categories',
        'GROUP' => 'group',
        'Collection' => 'custom_collection',
        'Size' => 'size',
        'Color' => 'color',
        'Brand' => 'brand',
        'Discount Price' => 'special_price',
        'Selling Price' => 'price',
        'Discount' => 'discount',
        'Stock' => 'qty',
        'Material' => 'material_1',
        'Sole' => 'sole',
        'Innersole' => 'innersole',
        'Interior' => 'interior',
        'Demountable' => 'demountable',
        'Closing' => 'closing',
        'Heel' => 'heel',
        'Plateaulevel' => 'plateaulevel',
        'Sizefits' => 'sizefits',
        'EAN' => 'ean',
        'Origin' => 'origin',
        'Material2' => 'material2',
        'Freetext' => 'freetext',
        'Photos' => 'additional_images',
        'Configurable Variations' => 'configurable_variations',
        'Url Key' => 'url_key',
        'Color Hex' => 'color_hex'
    );

    protected $headers;

    protected $itemImage;
    protected $_resources;

    /**
     * @var \Magento\Eav\Model\Entity\Attribute\SetFactory
     */
    protected $_attributeSetFactory;

    protected $imagesColumnArray = array(
        'base_image',
        'small_image',
        'thumbnail_image');


    protected $directory_list;
    /**
     * Import constructor.
     * @param \Magento\Eav\Model\Entity\Attribute\SetFactory $_attributeSetFactory
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectmanager,
        \Magento\Eav\Model\Entity\Attribute\SetFactory $attributeSetFactory,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Filesystem\DirectoryList $directory_list
    )
    {
        $this->directory_list = $directory_list;
        $this->_attributeSetFactory = $attributeSetFactory;
        $this->objectManager = $objectmanager;
        $this->_registry = $registry;
    }


    public function prepareData($data)
    {
        $this->headers = $this->getCsvHeaders($data[0]);
        $data[0] = $this->headers;
        $data = $this->getConvertedData($data);

        return $data;

    }

    public function prepareDataConfigurable($data)
    {
        $this->headers = $this->getCsvHeaders($data[0]);
        $data[0] = $this->headers;
        $data = $this->getConvertedDataConfigurable($data);

        return $data;

    }


    public function getCsvHeaders($headers)
    {
        $preparedHeaders = array();
        foreach ($headers as $item) {
            $preparedHeaders[] = $this->prepareHeaderItem($item);
        }
        $standardHeaders = array_keys($this->getStandardAttributes());
        for($i = 0; $i < count($standardHeaders); $i++)
        {
            if(!in_array($standardHeaders[$i], $preparedHeaders)){
                array_push($preparedHeaders, $standardHeaders[$i]);
            }
        }
        return $preparedHeaders;
    }

    public function getConvertedData($data)
    {
        $this->standardAttributes['product_type'] = 'simple';
        $convertedData = array();
        $counter = 0;
        foreach ($data as $index => $item)
        {
            if (!$index) {
                $convertedData[] = $this->headers;
                continue;
            }
            $preparedItems = array();
            $this->itemImage = '';
            for($i = 0; $i < count($this->headers); $i++)
            {
                $defaultValue = $this->checkDefaultValue($this->headers[$i]);
                if (!isset($item[$i]) && $defaultValue !== false) {
                    $preparedItems[] = $this->prepareItem($defaultValue, $this->headers[$i]);
                } else {
                    $preparedItems[] = $this->prepareItem($item[$i], $this->headers[$i]);
                }
            }
            $convertedData[] = $preparedItems;
            $counter++;
        }
        $convertedData = $this->checkNameAttribute($convertedData);
        $convertedData = $this->checkUrlKeyAttribute($convertedData);
        $convertedData = $this->addDefaultCategory($convertedData);

        return $convertedData;
    }

    public function getStandardAttributes()
    {
        return $this->standardAttributes;
    }

    public function getStandardAttribute($field)
    {
        if (isset($this->standardAttributes[$field])) {
            return $this->standardAttributes[$field];
        } else {
            return false;
        }
    }

    public function setStandardAttribute($field, $value)
    {
        $this->standardAttributes[$field] = $value;
    }

    public function getAttributesMapping()
    {
        return $this->attributesMapping;
    }

    public function getMappedAttribute($field)
    {
        if (isset($this->attributesMapping[$field])) {
            return $this->attributesMapping[$field];
        } else {
            return false;
        }
    }

    public function setAttributeSet($attributeSetId)
    {
        $attributeSet = $this->_attributeSetFactory->create()->load($attributeSetId);
        $this->setStandardAttribute('attribute_set_code',$attributeSet->getAttributeSetName());
    }

    public function setAttributesMapping($field, $value)
    {
        $this->attributesMapping[$field] = $value;
    }

    protected function encodToEncodUtf8($data)
    {
        $enclist = array(
            'UTF-8', 'ASCII', 'Windows-1251', 'Windows-1252', 'Windows-1254', 'ISO-8859-1',
            'ISO-8859-2',
            'ISO-8859-3',
            'ISO-8859-4',
            'ISO-8859-5',
            'ISO-8859-6',
            'ISO-8859-7',
            'ISO-8859-8',
            'ISO-8859-9',
            'ISO-8859-10',
            'ISO-8859-11',
            'ISO-8859-12',
            'ISO-8859-13',
            'ISO-8859-14',
            'ISO-8859-15',
            'ISO-8859-16'
        );

        $enclist = implode(',',$enclist);
        if (mb_detect_encoding($data,  $enclist, true)) {
            $encodedData = mb_convert_encoding($data, 'UTF-8', $enclist);
        } else {
            $encodedData = mb_convert_encoding($data, 'UTF-8', 'Windows-1251');
        }

        return $encodedData;
    }

    public function checkNameAttribute($convertedData)
    {
        $typeKey = array_search('product_type',$this->headers);
        $data = array();
        $colorData = array();
        if($nameColumnCount = $this->getColumnImdexByName('name')) {
            for($i = 1; $i < count($convertedData); $i++)
            {
                $convertedData[$i][$nameColumnCount] = $convertedData[$i][$this->getColumnImdexByName('brand')];
                if(!array_key_exists($convertedData[$i][$this->getColumnImdexByName('color')], $colorData)){
                    $colorData[$convertedData[$i][$this->getColumnImdexByName('color')]] = $convertedData[$i][$this->getColumnImdexByName('color_hex')];
                }
            }
            $data = $convertedData;

        } else {
            for($i = 0; $i < count($convertedData); $i++)
            {
                if ($i == 0) {
                    $data[$i] = $convertedData[$i];
                    array_push($data[$i] , 'name');
                } else {
                    $data[$i] = $convertedData[$i];
                    if($convertedData[$i][$typeKey] == 'simple'){
                        array_push($data[$i], $convertedData[$i][$this->getColumnImdexByName('brand')]);
                    }else{
                        array_push($data[$i], $convertedData[$i][$this->getColumnImdexByName('brand')]);
                    }
                    if(!array_key_exists($convertedData[$i][$this->getColumnImdexByName('color')], $colorData)){
                        $colorData[$convertedData[$i][$this->getColumnImdexByName('color')]] = $convertedData[$i][$this->getColumnImdexByName('color_hex')];
                    }
                }

            }
        }
        if(!$this->_registry->registry('color_data')){
            $this->_registry->unregister('color_data');
            $this->_registry->register('color_data', $colorData);
        }
        return $data;
    }

    public function checkUrlKeyAttribute($convertedData)
    {
        $model = $this->objectManager->create('Magento\Catalog\Model\Product');

        $typeKey = array_search('product_type',$this->headers);
        $data = array();
        $urlKey = false;
            for($i = 0; $i < count($convertedData); $i++)
            {
                if ($i == 0) {
                    $data[$i] = $convertedData[$i];
                    if(!in_array('url_key', $data[$i])){
                        $urlKey = true;
                        array_push($data[$i] , 'url_key');
                    }

                } else {
                    $data[$i] = $convertedData[$i];
                    if($urlKey){
                        if($convertedData[$i][$typeKey] == 'simple'){

                            $this->_resources = \Magento\Framework\App\ObjectManager::getInstance()
                                ->get('Magento\Framework\App\ResourceConnection');
                            $connection= $this->_resources->getConnection();
                            $urlKey = array();
                            $urlKey[$model->formatUrlKey($convertedData[$i][$this->getColumnImdexByName('brand')]. '-' . $convertedData[$i][$this->getColumnImdexByName('sku')]).'.html'] = $convertedData[$i][$this->getColumnImdexByName('sku')];
                            $urlKeyDuplicates = $connection->fetchAssoc(
                                $connection->select()->from(
                                    ['url_rewrite' => $connection->getTableName('url_rewrite')],
                                    ['request_path', 'store_id']
                                )->joinLeft(
                                    ['cpe' => $connection->getTableName('catalog_product_entity')],
                                    "cpe.entity_id = url_rewrite.entity_id"
                                )->where('request_path IN (?)', array_keys($urlKey))
                                    ->where('cpe.sku not in (?)', array_values($urlKey))
                            );
                            $urlKey= $model->formatUrlKey($convertedData[$i][$this->getColumnImdexByName('brand')]. '-' . $convertedData[$i][$this->getColumnImdexByName('sku')]);
                            if($urlKeyDuplicates){
                                array_push($data[$i], '');
                            }else{
                                array_push($data[$i], $urlKey);
                            }
                        }else{

                            $this->_resources = \Magento\Framework\App\ObjectManager::getInstance()
                                ->get('Magento\Framework\App\ResourceConnection');
                            $connection= $this->_resources->getConnection();
                            $urlKey = array();
                            $urlKey[$model->formatUrlKey($convertedData[$i][$this->getColumnImdexByName('brand')]. '-' . $convertedData[$i][$this->getColumnImdexByName('group')]).'.html'] = $convertedData[$i][$this->getColumnImdexByName('sku')];
                            $urlKeyDuplicates = $connection->fetchAssoc(
                                $connection->select()->from(
                                    ['url_rewrite' => $connection->getTableName('url_rewrite')],
                                    ['request_path', 'store_id']
                                )->joinLeft(
                                    ['cpe' => $connection->getTableName('catalog_product_entity')],
                                    "cpe.entity_id = url_rewrite.entity_id"
                                )->where('request_path IN (?)', array_keys($urlKey))
                                    ->where('cpe.sku not in (?)', array_values($urlKey))
                            );
                            $urlKey = $model->formatUrlKey($convertedData[$i][$this->getColumnImdexByName('brand')]. '-' . $convertedData[$i][$this->getColumnImdexByName('group')]);
                            if($urlKeyDuplicates){
                                array_push($data[$i], '');
                            }else{
                                array_push($data[$i], $urlKey);
                            }
                        }
                    }elseif($convertedData[$i][$typeKey] != 'simple'){
                        $this->_resources = \Magento\Framework\App\ObjectManager::getInstance()
                            ->get('Magento\Framework\App\ResourceConnection');
                        $connection= $this->_resources->getConnection();
                        $urlKey = array();
                        $urlKey[$model->formatUrlKey($convertedData[$i][$this->getColumnImdexByName('brand')]. '-' . $convertedData[$i][$this->getColumnImdexByName('group')]).'.html'] = $convertedData[$i][$this->getColumnImdexByName('sku')];
                        $urlKeyDuplicates = $connection->fetchAssoc(
                            $connection->select()->from(
                                ['url_rewrite' => $connection->getTableName('url_rewrite')],
                                ['request_path', 'store_id']
                            )->joinLeft(
                                ['cpe' => $connection->getTableName('catalog_product_entity')],
                                "cpe.entity_id = url_rewrite.entity_id"
                            )->where('request_path IN (?)', array_keys($urlKey))
                                ->where('cpe.sku not in (?)', array_values($urlKey))
                        );
                        $urlKey = $model->formatUrlKey($convertedData[$i][$this->getColumnImdexByName('brand')]. '-' . $convertedData[$i][$this->getColumnImdexByName('group')]);
                        if($urlKeyDuplicates){
                            array_push($data[$i], '');
                        }else{
                            array_push($data[$i], $urlKey);
                        }
                    }

                }
            }

        return $data;
    }

    public function addDefaultCategory($convertedData)
    {
        $defaultCategoryColumnIndex = $this->getColumnImdexByName('default_category');
        $categoriesColumnIndex = $this->getColumnImdexByName('categories');

        for($i = 1; $i < count($convertedData); $i++)
        {
            $defaultCategory = $this->getFirstProductCategory($convertedData[$i][$categoriesColumnIndex]);
            $convertedData[$i][$defaultCategoryColumnIndex] = $defaultCategory;
        }
        return $convertedData;
    }

    public function getFirstProductCategory($categories)
    {
        if (strpos($categories, ',') === false ){
            $defaultCategoryPath = $categories;
        } else {
            $categories = explode(',', $categories);
            $defaultCategoryPath =  $categories[0];
        }
        $categoryArray = explode('/', $defaultCategoryPath);
        $defaultCategory = end($categoryArray);
        $defaultCategory = trim($defaultCategory);
        return $defaultCategory;
    }

    public function getColumnImdexByName($name)
    {
        if ($index = array_keys($this->headers, $name)) {
            return $index[0];
        }
        return false;
    }

    public function checkDefaultValue($headerField)
    {
        if (array_key_exists($headerField, $this->getStandardAttributes())) {
            return $this->getStandardAttribute($headerField);
        }
        return false;
    }

    public function prepareItem($item, $column = false)
    {
        $convertedItem = trim($item);
        $convertedItem = $this->encodToEncodUtf8($convertedItem);
        $convertedItem = str_replace(',', '.', $convertedItem);
        $convertedItem = str_replace('|', ',', $convertedItem);
        if ($column == 'categories') {
            $convertedItem = str_replace(' > ', '/', $convertedItem);
            $convertedItem = $this->rootCategory . '/' . $convertedItem;
        } elseif ($column == 'additional_images') {
            $convertedItem = $this->prepareImagePaths($convertedItem);
        } elseif (in_array($column, $this->imagesColumnArray)) {
            $convertedItem = $this->prepareImagePaths($convertedItem, $column);
        } else {
            $convertedItem = str_replace('ROS?', 'ROS', $convertedItem);
        }

        return $convertedItem;
    }

    public function prepareHeaderItem($item)
    {
        $convertedItem = trim($item);
        $convertedItem = $this->encodToEncodUtf8($convertedItem);
        if (array_key_exists($convertedItem, $this->getAttributesMapping())) {
            $convertedItem = $this->getMappedAttribute($convertedItem);
        } else {
            $convertedItem = str_replace(' ','_', $convertedItem);
            $convertedItem = strtolower($convertedItem);
        }

        return $convertedItem;
    }

    public function prepareImagePaths($path, $column = false)
    {
        if (in_array($column , $this->imagesColumnArray)){
            if(file_exists($this->directory_list->getPath('media'). '/import' . $this->itemImage)){
                $path = $this->itemImage;
            } else {
                $path = '';
            }

        } elseif (gettype($path) == 'string' && $path != '') {
            $path = str_replace(self::IMAGE_URL_TO_CUT, '',$path);
            if (!preg_match("/^[a-zA-Z\d\/\.\_\-\|]*$/", $path)) {
                $path = '';
            }
            $path = str_replace('|', ',',$path);
            $images = explode(',', $path);

            $newPath = '';
            foreach($images as $index => $image) {
                if(file_exists($this->directory_list->getPath('media'). '/import' .$image)){
                    if (strlen($image) < 99 && $index == 0) {
                        $newPath .=  $image;
                    } elseif(strlen($image) < 99) {
                        $newPath .= ',' . $image;
                    }
                }

            }
            $path = $newPath;
            $this->itemImage = $images[0];
        }
        return $path;
    }

    public function getConvertedDataConfigurable($data)
    {
        $convertedData = array();
        $counter = 0;
        $group = '';
        $groupKey = array_search('group',$this->headers);
        $colorKey = array_search('color',$this->headers);
        $sizeKey = array_search('size',$this->headers);
        $variaionsKey = array_search('configurable_variations',$this->headers);
        $skuKey = array_search('SKU',$this->headers);
        $configurableVariation = '';
        $this->standardAttributes['product_type'] = 'configurable';
        $this->standardAttributes['visibility'] = 'catalogus| zoeken';
        $countElements = count($data)-1;
        foreach ($data as $index => $item)
        {
            if (!$index) {
                $convertedData[] = $this->headers;
                continue;
            }
            $preparedItems = array();
            $this->itemImage = '';
            if(!$group || $group != $item[$groupKey]){
                for($i = 0; $i < count($this->headers); $i++)
                {
                    $defaultValue = $this->checkDefaultValue($this->headers[$i]);
                    if (!isset($item[$i]) && $defaultValue !== false) {
                        $preparedItems[] = $this->prepareItem($defaultValue, $this->headers[$i]);
                    } else {
                        if($this->headers[$i] == 'sku'){
                            $preparedItems[] = $this->prepareItem($item[$i].'-conf', $this->headers[$i]);
                        }else{
                            $preparedItems[] = $this->prepareItem($item[$i], $this->headers[$i]);
                        }

                    }
                }
                if($configurableVariation){
                    $convertedData[$counter][$variaionsKey] = $configurableVariation;
                }
                $configurableVariation = '';
                $configurableVariation .= 'sku='.$this->prepareItem($item[$skuKey]) . ',color='.$this->prepareItem($item[$colorKey]) . ',size='.$this->prepareItem($item[$sizeKey]);
                $group = $item[$groupKey];
                $convertedData[] = $preparedItems;
                $counter++;
            }else{
                $configurableVariation .= '|sku='.$this->prepareItem($item[$skuKey]) . ',color='.$this->prepareItem($item[$colorKey]) . ',size='.$this->prepareItem($item[$sizeKey]);
            }
            if($countElements == $index && $configurableVariation){
                $convertedData[$counter][$variaionsKey] = $configurableVariation;
            }
        }
        $convertedData = $this->checkNameAttribute($convertedData);
        $convertedData = $this->checkUrlKeyAttribute($convertedData);
        $convertedData = $this->addDefaultCategory($convertedData);

        return $convertedData;
    }
}
