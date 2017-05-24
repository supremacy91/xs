<?php

namespace IntechSoft\CustomImport\Model;

use \Magento\Framework\App\Filesystem\DirectoryList;
use \Magento\ImportExport\Model\Import\Adapter as ImportAdapter;
use \Magento\Eav\Model\Config;

class Attributes extends \Magento\Catalog\Model\AbstractModel
{
    
    protected $attrOptionCollectionFactory;
    /**
     * @var \Magento\Eav\Model\Entity\Attribute\SetFactory
     */
    protected $_attributeSetFactory;

    /**
     * @var \Magento\Eav\Model\ResourceModel\Entity\Attribute\Group\CollectionFactoryt
     */
    protected $_groupCollectionFactory;

    /**
     * @var \Magento\Catalog\Helper\Product
     */
    protected $attributeFactory;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory
     */
    protected $productHelper;

    protected $_entityTypeId;

    protected $csvFile;

    /**
     * @var \Magento\Eav\Model\AttributeRepository
     */
    protected $_attributeRepository;

    /**
     * @var \Magento\Eav\Api\AttributeOptionManagementInterface
     */
    protected $_attributeOptionManagement;

    /**
     * @var \Magento\Framework\File\Csv $csvProcessor
     */
    protected $csvProcessor;

    /**
     * @var \Magento\Eav\Api\Data\AttributeOptionLabelInterfaceFactory
     */
    protected $optionLabelFactory;

    /**
     * @var \Magento\Eav\Api\Data\AttributeOptionInterfaceFactory
     */
    protected $optionFactory;

    protected $_collectedAttributes = array();

    protected $csvFileData = array();

    protected $_selectAttributes = array('color', 'size');

    public $allowToContinueImport = true;

    protected $_registry;

    protected $attrbuteSettings = array(
        'attribute_set_id' => '4',
        'attribute_group_code' => 'product-details'
    );

    /**
     * Attributes constructor.
     * @param \Magento\Eav\Model\Entity\Attribute\SetFactory  $attributeSetFactoryr
     * @param \Magento\Eav\Model\ResourceModel\Entity\Attribute\Group\CollectionFactory $groupCollectionFactory
     * @param \Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory $attributeFactory
     * @param \Magento\Catalog\Helper\Product $productHelper
     * @param \Magento\Eav\Model\AttributeRepository $attributeRepository
     * @param \Magento\Eav\Api\AttributeOptionManagementInterface $attributeOptionManagement
     * @param \Magento\Framework\File\Csv $csvProcessor
     * @param \Magento\Framework\ObjectManagerInterface $objectmanager
     * @param \Magento\Eav\Api\Data\AttributeOptionLabelInterfaceFactory $optionLabelFactory
     * @param \Magento\Eav\Api\Data\AttributeOptionInterfaceFactory $optionFactory
     */

    public function __construct(
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory $attrOptionCollectionFactory,
        \Magento\Eav\Model\Entity\Attribute\SetFactory  $attributeSetFactory,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Group\CollectionFactory $groupCollectionFactory,
        \Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory $attributeFactory,
        \Magento\Catalog\Helper\Product $productHelper,
        \Magento\Eav\Model\AttributeRepository $attributeRepository,
        \Magento\Eav\Api\AttributeOptionManagementInterface $attributeOptionManagement,
        \Magento\Framework\File\Csv $csvProcessor,
        \Magento\Framework\ObjectManagerInterface $objectmanager,
        \Magento\Eav\Api\Data\AttributeOptionLabelInterfaceFactory $optionLabelFactory,
        \Magento\Eav\Api\Data\AttributeOptionInterfaceFactory $optionFactory,
        \Magento\Framework\Registry $registry
    )
    {
        $this->attrOptionCollectionFactory = $attrOptionCollectionFactory;
        $this->_attributeSetFactory = $attributeSetFactory;
        $this->productHelper = $productHelper;
        $this->_groupCollectionFactory = $groupCollectionFactory;
        $this->attributeFactory = $attributeFactory;
        $this->_attributeRepository = $attributeRepository;
        $this->_attributeOptionManagement = $attributeOptionManagement;
        $this->csvProcessor = $csvProcessor;
        $this->optionLabelFactory = $optionLabelFactory;
        $this->optionFactory = $optionFactory;
        $this->objectManager = $objectmanager;
        $this->_registry = $registry;

        $this->_entityTypeId = $this->objectManager->create(
            'Magento\Eav\Model\Entity'
        )->setType(
            \Magento\Catalog\Model\Product::ENTITY
        )->getTypeId();
    }

    /**
     * @param $csvFile
     */
    public function checkAddAttributes($csvFile)
    {
        $this->setCsvFile($csvFile);
        $this->setCsvFileData();
        $this->collectAttributes();
        $this->collectAttributeOptions();
        $this->addNewAttributes();
    }

    /**
     * set csv file name
     * @param $csvFile
     */
    protected function setCsvFile($csvFile)
    {
        $this->csvFile = $csvFile;
    }

    public function setAttributeSettings($settings)
    {
        if (is_array($settings) && count($settings) > 0) {
            foreach ($settings as $name => $value) {
                if (isset($this->attrbuteSettings[$name]) && $value != ''){
                    $this->attrbuteSettings[$name] = $value;
                }
            }
        }

    }

    /**
     * create attributes
     * @return $this
     */
    public function createAttributesAndOptions($attributeCode, $type)
    {
        $attribute = $this->createAttribute($attributeCode, $type);

        if ($attribute &&  $attributeCode != 'configurable_variations' && $attributeCode != 'additional_images' && $attributeCode != 'color_hex') {
            $attributeSet = $this->_attributeSetFactory->create();
            $attributeSet->setEntityTypeId($this->_entityTypeId)->load('Default');
            $productDetailsGroupe = $this->_groupCollectionFactory->create()
                ->addFieldToFilter('attribute_group_code', $this->attrbuteSettings['attribute_group_code'])
                ->addFieldToFilter('attribute_set_id', $this->attrbuteSettings['attribute_set_id'])
                ->getFirstItem();
            $attribute->setAttributeSetId($productDetailsGroupe->getAttributeSetId());
            $attribute->setAttributeGroupId($productDetailsGroupe->getAttributeGroupId());
            $attribute->save();
            if ($type == 'select') {
                $this->prepareOptions($attribute);
            }
        }

        return $this;
    }

    /**
     * @param $attributeCode
     * @return \Magento\Catalog\Model\ResourceModel\Eav\Attribute
     */
    protected function createAttribute($attributeCode, $type)
    {
        if ($type == 'select') {
            $attributeCode = array_keys($attributeCode)[0];
        }
        $attribute = $this->attributeFactory->create();
        $attribute->loadByCode(\Magento\Catalog\Model\Product::ENTITY, $attributeCode);
        $swatchInputType = 'select';
        if (in_array($attributeCode, $this->_selectAttributes)){
            if($attributeCode == 'color') {
                $swatchInputType = 'swatch_visual';
            } else {
                $swatchInputType = 'swatch_text';
            }
        }
        if (is_null($attribute->getId()) && $attributeCode != 'qty') {
            $attribute->addData([
                'entity_type_id'    => $this->_entityTypeId,
                'attribute_code'    => $attributeCode,
                'frontend_input'    => $type,
                'is_required'       => 0,
                'source_model'      => $this->productHelper->getAttributeSourceModelByInputType($type),
                'backend_model'     => $this->productHelper->getAttributeBackendModelByInputType($type),
                'backend_type'      => $attribute->getBackendTypeByInput($type),
                'frontend_label'    => array(ucfirst($attributeCode)),
                'is_global'         => \Magento\Catalog\Model\ResourceModel\Eav\Attribute::SCOPE_GLOBAL,
                /*'swatch_input_type' => ($attributeCode == 'color' ? 'swatch_visual' :
                    ($attributeCode == 'size' ? 'swatch_text' : 'select')),*/
                'swatch_input_type' => $swatchInputType,
                'is_unique'         => 0,
                'is_user_defined'   => 1
            ]);

            return $attribute;
        }

        if ($type == 'select') {
            $this->prepareOptions($attribute);
        }

        return false;
    }

    /**
     * prepare options for attributes
     * @param $attribute
     */
    public function prepareOptions($attribute)
    {

        if ($newOptions = $this->getNewOptions($attribute)){
            $attributeName = $attribute;
            $attribute = $this->_attributeRepository->get('catalog_product', $attributeName);
            $attributeId = $attribute->getAttributeId();
            $attributeCode = $attribute->getAttributeCode();


            foreach ($newOptions as $optionName) {
                $optionLabel = $this->optionLabelFactory->create();
                $optionLabel->setStoreId(0);
                $optionLabel->setLabel($optionName);
                //$optionLabel->setDefault($optionName);

                $option = $this->optionFactory->create();
                $option->setLabel($optionLabel);
                $option->setStoreLabels([$optionLabel]);
                $option->setSortOrder(0);
                $option->setIsDefault(false);

                if (!$this->_attributeOptionManagement->add('catalog_product', $attributeCode, $option)) {
                    $this->allowToContinueImport;
                }
            }
        }
    }

    /**
     * collect attributes from csv file
     */
    protected function collectAttributes()
    {
        foreach ($this->csvFileData[0] as $attributeCode) {
            $this->_collectedAttributes[] = $attributeCode;
        }
    }

    /**
     * collect attributes options for attributes type - select
     */
    protected function collectAttributeOptions()
    {
        $collectedOptions = array();
        foreach ($this->csvFileData as $index => $item) {
            if ($index == 0){
                continue;
            }
            if (count($item) == count($this->_collectedAttributes)) {
                for($i = 0 ; $i < count($this->_collectedAttributes); $i++){
                    if (in_array($this->_collectedAttributes[$i], $this->_selectAttributes)) {
                        $collectedOptions[$this->_collectedAttributes[$i]][] = $item[$i];
                    }

                }
            }
        }

        foreach ($collectedOptions as $attributeName => $options) {
            $attributeIndex = $this->getColumnImdexByName($attributeName);
            $this->_collectedAttributes[$attributeIndex] = array($attributeName => array_unique($options));
        }
    }

    /**
     * return column index
     * @param $name
     * @return array|bool|int|string
     */
    public function getColumnImdexByName($name)
    {
        if (in_array($name, $this->_selectAttributes)) {
            foreach ($this->_collectedAttributes as $index => $attribute) {
                if (isset($attribute[$name]) && is_array($attribute)){
                    return $index;
                }
            }
        }

        if ($index = array_keys($this->_collectedAttributes, $name)) {
            return $index[0];
        }
        return false;
    }

    /**
     * set data from csv file
     */
    protected function setCsvFileData()
    {
        $this->csvFileData = $this->csvProcessor->getData($this->csvFile);
    }

    /**
     * add new attributes
     */
    protected function addNewAttributes()
    {
        foreach ($this->_collectedAttributes as $attributeCode) {
            if(is_array($attributeCode)){
                $this->createAttributesAndOptions($attributeCode, 'select');
            } else {
                $this->createAttributesAndOptions($attributeCode, 'text');
            }
        }
    }

    /**
     * return new options for attribute type select
     * @param $attributeName
     * @return array|bool
     */
    protected function getNewOptions($attributeName)
    {
        $newOptions = array();
        $attributeId = $this->_attributeRepository->get('catalog_product', $attributeName)->getAttributeId();
        $options = $this->_attributeOptionManagement->getItems('catalog_product', $attributeId);
        $attributeIndex = $this->getColumnImdexByName($attributeName->getAttributeCode());
        $newOptions = $this->_collectedAttributes[$attributeIndex][$attributeName->getAttributeCode()];
        foreach($options as $option) {
            if (in_array($option->getLabel(), $newOptions)) {
                $newOptions = array_diff($newOptions, array($option->getLabel()));
            }
        }
        if (!count($newOptions)) {
            return false;
        }
        return $newOptions;
    }

    public function convertSizeToSwatches($storeId)
    {
        $attribute = $this->_attributeRepository->get(\Magento\Catalog\Model\Product::ENTITY, 'size');
        if (!$attribute) {
            return;
        }
        $existOption['option'] = $this->addExistingOptions($attribute);
        $attributeData['swatchtext'] = $this->getOptionSwatchText($existOption,$storeId);
        $attribute->addData($attributeData);
        $attribute->save();
    }


    public function convertColorToSwatches()
    {
        $colorMap = $this->_registry->registry('color_data');
        $this->_registry->unregister('color_data');
        if($colorMap) {
            $attribute = $this->_attributeRepository->get(\Magento\Catalog\Model\Product::ENTITY, 'color');
            if (!$attribute) {
                return;
            }
            $attributeData['option'] = $this->addExistingOptions($attribute);
            $attributeData['frontend_input'] = 'select';
            $attributeData['swatch_input_type'] = 'visual';
            $attributeData['update_product_preview_image'] = 1;
            $attributeData['use_product_image_for_swatch'] = 0;
            $attributeData['optionvisual'] = $this->getOptionSwatch($attributeData);
            $attributeData['swatchvisual'] = $this->getOptionSwatchVisual($attributeData, $colorMap);
            $attribute->addData($attributeData);
            $attribute->save();
        }
    }

    /**
     * @param array $attributeData
     * @return array
     */
    private function getOptionSwatchVisual(array $attributeData, $colorMap)
    {

        $optionSwatch = ['value' => []];

            foreach ($attributeData['option'] as $optionKey => $optionValue) {
                if(isset($optionValue)){
                    if (substr($optionValue, 0, 1) == '#' && strlen($optionValue) == 7) {
                        $optionSwatch['value'][$optionKey] = $optionValue;
                    } else if (array_key_exists($optionValue, $colorMap)) {
                        $optionSwatch['value'][$optionKey] = $colorMap[$optionValue];
                    } else {
                        $optionSwatch['value'][$optionKey] = '#ffffff';
                    }
                }
            }
        return $optionSwatch;
    }

    /**
     * @param array $attributeData
     * @return array
     */
    protected function getOptionSwatch(array $attributeData)
    {
        $optionSwatch = ['order' => [], 'value' => [], 'delete' => []];
        $i = 0;
        foreach ($attributeData['option'] as $optionKey => $optionValue) {
            $optionSwatch['delete'][$optionKey] = '';
            $optionSwatch['order'][$optionKey] = (string)$i++;
            $optionSwatch['value'][$optionKey] = [$optionValue, ''];
        }
        return $optionSwatch;
    }

    /**
     * @param $attributeId
     * @return void
     */
    private function loadOptionCollection($attributeId)
    {
        if (empty($this->optionCollection[$attributeId])) {
            $this->optionCollection[$attributeId] = $this->attrOptionCollectionFactory->create()
                ->setAttributeFilter($attributeId)
                ->setPositionOrder('asc', true)
                ->load();
        }
    }

    private function addExistingOptions($attribute)
    {
        $options = [];
        $attributeId = $attribute->getId();
        if ($attributeId) {
            $this->loadOptionCollection($attributeId);
            /** @var \Magento\Eav\Model\Entity\Attribute\Option $option */
            foreach ($this->optionCollection[$attributeId] as $option) {
                $options[$option->getId()] = $option->getValue();
            }
        }
        return $options;
    }

    private function getOptionSwatchText(array $attributeData, $storeId)
    {
        $optionSwatch = ['value' => []];
        foreach ($attributeData['option'] as $optionKey => $optionValue) {
            $optionSwatch['value'][$optionKey] = array($storeId=>$optionValue);
        }
        return $optionSwatch;
    }

}