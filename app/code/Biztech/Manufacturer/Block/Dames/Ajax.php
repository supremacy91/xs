<?php
namespace Biztech\Manufacturer\Block\Dames;
use Biztech\Manufacturer\Block\BaseBlock;
use Biztech\Manufacturer\Block\Context;

class Ajax extends BaseBlock {
	protected $_defaultToolbarBlock = 'Magento\Catalog\Block\Product\ProductList\Toolbar';
	protected $_defaultColumnCount = 3;
	protected $_columnCountLayoutDepend = [];
	protected $_collection;
	protected $_helper;
	protected $_config;

	/**
	 * Ajax constructor.
	 * @param Context $context
	 */
	public function __construct(
		Context $context
	) {
		parent::__construct($context);
		$this->_helper = $context->getManufacturerHelper();
		$this->_config = $context->getConfig();
		$this->_collection = $context->getManufacturerHelper()->getManufacturerCollection()
			->addFieldToFilter('manufacturer_name', array('nlike' => '%KIDS'));
		$this->setCollection($this->newCollection());
	}

	protected function newCollection() {
		$char = $this->getRequest()->getParam('char');
		$brandType = !empty($this->getRequest()->getParam('brandtype')) ? $this->getRequest()->getParam('brandtype') : "";

		foreach ($this->_collection as $manufacturer) {

			/* if ($brandType=='kids') {
				                if (!(substr($manufacturer->getManufacturerName(), -4)=='KIDS')) {
				                    continue;
				                }
				            } else if ($brandType=='general') {
				                if (substr($manufacturer->getManufacturerName(), -4)=='KIDS') {
				                continue;
				                }
			*/
			if ($char != 'All') {
				if (strtolower(substr($manufacturer->getBrandName(), 0, 1)) != strtolower($char)) {
					$this->_collection->removeItemByKey($manufacturer->getManufacturerId());
				}
			}
		}
		$ids = [];
		foreach ($this->_collection as $manufacturer) {
			/* if ($brandType=='kids') {
				                if (!(substr($manufacturer->getManufacturerName(), -4)=='KIDS')) {
				                    continue;
				                }
				            } else if ($brandType=='general') {
				                if (substr($manufacturer->getManufacturerName(), -4)=='KIDS') {
				                continue;
				                }
			*/

			$ids[] = $manufacturer->getManufacturerId();
		}
		if (!sizeof($ids)) {
			$ids = '';
		}

		$newCollection = $this->getHelper()->getNewManufacturerCollection($ids);

		return $newCollection;

	}

	public function getHelper() {
		return $this->_helper;
	}

	public function _construct() {

		parent::_construct();
	}

	public function getConfig() {
		return $this->_config;
	}

	/**
	 * Retrieve current view mode
	 *
	 * @return string
	 */
	public function getMode() {
		return $this->getChildBlock('toolbar')->getCurrentMode();
	}

	public function getManufacturerUrl($manufacturer) {
		$url = $this->getUrl('merken/' . $manufacturer->getUrlKey(), []);
		return $url;
	}

	public function getToolbarHtml() {
		return $this->getChildHtml('toolbar');
	}

	public function getPagerHtml() {
		return $this->getChildHtml('pager');
	}

	protected function _prepareLayout() {
		parent::_prepareLayout();

		$toolbar = $this->getToolbarBlock();

		$collection = $this->getCollection();

		if ($orders = $this->getAvailableOrders()) {
			$toolbar->setAvailableOrders($orders);
		}
		if ($sort = $this->getSortBy()) {
			$toolbar->setDefaultOrder($sort);
		}
		if ($dir = $this->getDefaultDirection()) {
			$toolbar->setDefaultDirection($dir);
		}
		$toolbar->setCollection($collection);

		if ($toolbar->getData('_current_grid_direction') == 'asc') {
			$collection->setOrder('manufacturer_name', 'ASC');
		} else if ($toolbar->getData('_current_grid_direction') == 'desc') {
			$collection->setOrder('manufacturer_name', 'DESC');
		}

		$this->setChild('toolbar', $toolbar);
		$this->getCollection()->load();
		/*if ($this->getCollection()) {
			            $pager = $this->getLayout()->createBlock('Magento\Theme\Block\Html\Pager')->setCollection($this->getCollection());
			            $this->setChild('pager', $pager);
		*/
		return $this;
	}

	/**
	 * Retrieve Toolbar block
	 *
	 * @return \Magento\Catalog\Block\Product\ProductList\Toolbar
	 */
	public function getToolbarBlock() {
		$blockName = $this->getToolbarBlockName();
		if ($blockName) {
			$block = $this->getLayout()->getBlock($blockName);
			if ($block) {
				return $block;
			}
		}
		$block = $this->getLayout()->createBlock($this->_defaultToolbarBlock, uniqid(microtime()));
		$block->setData('_current_limit', 'ALL');
		return $block;
	}

	public function getAvailableOrders() {
		return ['position' => 'Position', 'brand_name' => 'Name'];
	}

	public function getSortBy() {
		return 'manufacturer_id';
	}

	public function getDefaultDirection() {
		return 'asc';
	}

}
