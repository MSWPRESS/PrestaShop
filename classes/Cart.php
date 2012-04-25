<?php
/*
* 2007-2012 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2012 PrestaShop SA
*  @version  Release: $Revision: 7506 $
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

class CartCore extends ObjectModel
{
	public $id;

	public $id_shop_group;

	public $id_shop;

	/** @var integer Customer delivery address ID */
	public $id_address_delivery;

	/** @var integer Customer invoicing address ID */
	public $id_address_invoice;

	/** @var integer Customer currency ID */
	public $id_currency;

	/** @var integer Customer ID */
	public $id_customer;

	/** @var integer Guest ID */
	public $id_guest;

	/** @var integer Language ID */
	public $id_lang;

	/** @var boolean True if the customer wants a recycled package */
	public $recyclable = 1;

	/** @var boolean True if the customer wants a gift wrapping */
	public $gift = 0;

	/** @var string Gift message if specified */
	public $gift_message;

	/** @var string Object creation date */
	public $date_add;

	/** @var string secure_key */
	public $secure_key;

	/* @var integer Carrier ID */
	public $id_carrier = 0;

	/** @var string Object last modification date */
	public $date_upd;

	public $checkedTos = false;
	public $pictures;
	public $textFields;

	public $delivery_option;

	/** @var boolean Allow to seperate order in multiple package in order to recieve as soon as possible the available products */
	public $allow_seperated_package = false;

	protected static $_nbProducts = array();
	protected static $_isVirtualCart = array();

	protected $_products = null;
	protected static $_totalWeight = array();
	protected $_taxCalculationMethod = PS_TAX_EXC;
	protected static $_carriers = null;
	protected static $_taxes_rate = null;
	protected static $_attributesLists = array();

	/**
	 * @see ObjectModel::$definition
	 */
	public static $definition = array(
		'table' => 'cart',
		'primary' => 'id_cart',
		'fields' => array(
			'id_shop_group' => 			array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
			'id_shop' => 				array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
			'id_address_delivery' => 	array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
			'id_address_invoice' => 	array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
			'id_carrier' => 			array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
			'id_currency' => 			array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true),
			'id_customer' => 			array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
			'id_guest' => 				array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
			'id_lang' => 				array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true),
			'recyclable' => 			array('type' => self::TYPE_BOOL, 'validate' => 'isBool'),
			'gift' => 					array('type' => self::TYPE_BOOL, 'validate' => 'isBool'),
			'gift_message' => 			array('type' => self::TYPE_STRING, 'validate' => 'isMessage'),
			'delivery_option' => 		array('type' => self::TYPE_STRING),
			'secure_key' => 			array('type' => self::TYPE_STRING, 'size' => 32),
			'allow_seperated_package' =>array('type' => self::TYPE_BOOL, 'validate' => 'isBool'),
			'date_add' => 				array('type' => self::TYPE_DATE, 'validate' => 'isDateFormat'),
			'date_upd' => 				array('type' => self::TYPE_DATE, 'validate' => 'isDateFormat'),
		),
	);

	protected $webserviceParameters = array(
		'fields' => array(
		'id_address_delivery' => array('xlink_resource' => 'addresses'),
		'id_address_invoice' => array('xlink_resource' => 'addresses'),
		'id_currency' => array('xlink_resource' => 'currencies'),
		'id_customer' => array('xlink_resource' => 'customers'),
		'id_guest' => array('xlink_resource' => 'guests'),
		'id_lang' => array('xlink_resource' => 'languages'),
		),
		'associations' => array(
			'cart_rows' => array('resource' => 'cart_row', 'virtual_entity' => true, 'fields' => array(
				'id_product' => array('required' => true, 'xlink_resource' => 'products'),
				'id_product_attribute' => array('required' => true, 'xlink_resource' => 'combinations'),
				'quantity' => array('required' => true),
				)
			),
		),
	);

	const ONLY_PRODUCTS = 1;
	const ONLY_DISCOUNTS = 2;
	const BOTH = 3;
	const BOTH_WITHOUT_SHIPPING = 4;
	const ONLY_SHIPPING = 5;
	const ONLY_WRAPPING = 6;
	const ONLY_PRODUCTS_WITHOUT_SHIPPING = 7;
	const ONLY_PHYSICAL_PRODUCTS_WITHOUT_SHIPPING = 8;

	public function __construct($id = null, $id_lang = null)
	{
		parent::__construct($id, $id_lang);
		if ($this->id_customer)
		{
			if (isset(Context::getContext()->customer) && Context::getContext()->customer->id == $this->id_customer)
				$customer = Context::getContext()->customer;
			else
				$customer = new Customer((int)$this->id_customer);
			$this->_taxCalculationMethod = Group::getPriceDisplayMethod((int)$customer->id_default_group);

			if ((!$this->secure_key || $this->secure_key == '-1') && $customer->secure_key)
			{
				$this->secure_key = $customer->secure_key;
				$this->save();
			}
		}
		else
			$this->_taxCalculationMethod = Group::getDefaultPriceDisplayMethod();

	}

	public function add($autodate = true, $null_values = false)
	{
		if (!$this->id_lang)
			$this->id_lang = Configuration::get('PS_LANG_DEFAULT');

		$return = parent::add($autodate);
		Hook::exec('actionCartSave');

		return $return;
	}

	public function update($null_values = false)
	{
		if (isset(self::$_nbProducts[$this->id]))
			unset(self::$_nbProducts[$this->id]);

		if (isset(self::$_totalWeight[$this->id]))
			unset(self::$_totalWeight[$this->id]);

		$this->_products = null;
		$return = parent::update();
		Hook::exec('actionCartSave');

		return $return;
	}
	
	/**
	 * Update the address id of the cart
	 * 
	 * @param int $id_address Current address id to change
	 * @param int $id_address_new New address id
	 */
	public function updateAddressId($id_address, $id_address_new)
	{
		$to_update = false;
		if ($this->id_address_invoice == $id_address)
		{
			$to_update = true;
			$this->context->cart->id_address_invoice = $id_address_new;
		}
		if ($this->id_address_delivery == $id_address)
		{
			$to_update = true;
			$this->id_address_delivery = $id_address_new;
		}
		if ($to_update)
			$this->update();
		
		$sql = 'UPDATE `'._DB_PREFIX_.'cart_product`
		SET `id_address_delivery` = '.(int)$id_address_new.'
		WHERE  `id_cart` = '.(int)$this->id.'
			AND `id_address_delivery` = '.(int)$id_address;
		Db::getInstance()->execute($sql);
	}

	public function delete()
	{
		if ($this->OrderExists()) //NOT delete a cart which is associated with an order
			return false;

		$uploaded_files = Db::getInstance()->executeS('
			SELECT cd.`value`
			FROM `'._DB_PREFIX_.'customized_data` cd
			INNER JOIN `'._DB_PREFIX_.'customization` c ON (cd.`id_customization`= c.`id_customization`)
			WHERE cd.`type`= 0 AND c.`id_cart`='.(int)$this->id
		);

		foreach ($uploaded_files as $must_unlink)
		{
			unlink(_PS_UPLOAD_DIR_.$must_unlink['value'].'_small');
			unlink(_PS_UPLOAD_DIR_.$must_unlink['value']);
		}

		Db::getInstance()->execute('
			DELETE FROM `'._DB_PREFIX_.'customized_data`
			WHERE `id_customization` IN (
				SELECT `id_customization`
				FROM `'._DB_PREFIX_.'customization`
				WHERE `id_cart`='.(int)$this->id.'
			)'
		);

		Db::getInstance()->execute('
			DELETE FROM `'._DB_PREFIX_.'customization`
			WHERE `id_cart` = '.(int)$this->id
		);

		if (!Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'cart_cart_rule` WHERE `id_cart` = '.(int)$this->id)
		 || !Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'cart_product` WHERE `id_cart` = '.(int)$this->id))
			return false;

		return parent::delete();
	}

	public static function getTaxesAverageUsed($id_cart)
	{
		$cart = new Cart((int)$id_cart);
		if (!Validate::isLoadedObject($cart))
			die(Tools::displayError());

		if (!Configuration::get('PS_TAX'))
			return 0;

		$products = $cart->getProducts();
		$total_products_moy = 0;
		$ratio_tax = 0;

		if (!count($products))
			return 0;

		foreach ($products as $product) // products refer to the cart details
		{
			if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_invoice')
				$address_id = (int)$cart->id_address_invoice;
			else
				$address_id = (int)$product->id_address_delivery; // Get delivery address of the product from the cart
			if (!Address::addressExists($address_id))
				$address_id = null;
			
			$total_products_moy += $product['total_wt'];
			$ratio_tax += $product['total_wt'] * Tax::getProductTaxRate(
				(int)$product['id_product'],
				(int)$address_id
			);
		}

		if ($total_products_moy > 0)
			return $ratio_tax / $total_products_moy;

		return 0;
	}

	/**
	 * @deprecated 1.5.0, use Cart->getCartRules()
	 */
	public function getDiscounts($lite = false, $refresh = false)
	{
		Tools::displayAsDeprecated();
		return $this->getCartRules();
	}

	public function getCartRules($filter = CartRule::FILTER_ACTION_ALL)
	{
		// If the cart has not been saved, then there can't be any cart rule applied
		if (!CartRule::isFeatureActive() || !$this->id)
			return array();

		$cache_key = 'Cart::getCartRules'.$this->id.'-'.$filter;
		if (!Cache::isStored($cache_key))
		{
			$result = Db::getInstance()->executeS('
				SELECT *
				FROM `'._DB_PREFIX_.'cart_cart_rule` cd
				LEFT JOIN `'._DB_PREFIX_.'cart_rule` cr ON cd.`id_cart_rule` = cr.`id_cart_rule`
				LEFT JOIN `'._DB_PREFIX_.'cart_rule_lang` crl ON (
					cd.`id_cart_rule` = crl.`id_cart_rule`
					AND crl.id_lang = '.(int)$this->id_lang.'
				)
				WHERE `id_cart` = '.(int)$this->id.'
				'.($filter == CartRule::FILTER_ACTION_SHIPPING ? 'AND free_shipping = 1' : '').'
				'.($filter == CartRule::FILTER_ACTION_GIFT ? 'AND gift_product != 0' : '').'
				'.($filter == CartRule::FILTER_ACTION_REDUCTION ? 'AND (reduction_percent != 0 OR reduction_amount != 0)' : '')
			);
			Cache::store($cache_key, $result);
		}
		$result = Cache::retrieve($cache_key);
		
		// Define virtual context to prevent case where the cart is not the in the global context
		$virtual_context = Context::getContext()->cloneContext();
		$virtual_context->cart = $this;
		
		foreach ($result as &$row)
		{
			$row['obj'] = new CartRule($row['id_cart_rule'], (int)$this->id_lang);
			$row['value_real'] = $row['obj']->getContextualValue(true, $virtual_context, $filter);
			$row['value_tax_exc'] = $row['obj']->getContextualValue(false, $virtual_context, $filter);
			
			// Retro compatibility < 1.5.0.2
			$row['id_discount'] = $row['id_cart_rule'];
			$row['description'] = $row['name'];
		}

		return $result;
	}

	public function getDiscountsCustomer($id_cart_rule)
	{
		if (!CartRule::isFeatureActive())
			return 0;

		return Db::getInstance()->getValue('
			SELECT COUNT(*)
			FROM `'._DB_PREFIX_.'cart_cart_rule`
			WHERE `id_cart_rule` = '.(int)$id_cart_rule.' AND `id_cart` = '.(int)$this->id
		);
	}

	public function getLastProduct()
	{
		$sql = '
			SELECT `id_product`, `id_product_attribute`, id_shop
			FROM `'._DB_PREFIX_.'cart_product`
			WHERE `id_cart` = '.(int)$this->id.'
			ORDER BY `date_add` DESC';

		$result = Db::getInstance()->getRow($sql);
		if ($result && isset($result['id_product']) && $result['id_product'])
			foreach ($this->getProducts() as $product)
			if ($result['id_product'] == $product['id_product']
				&& (
					!$result['id_product_attribute']
					|| $result['id_product_attribute'] == $product['id_product_attribute']
				))
				return $product;

		return false;
	}

	/**
	 * Return cart products
	 *
	 * @result array Products
	 */
	public function getProducts($refresh = false, $id_product = false, $id_country = null)
	{
		if (!$this->id)
			return array();
		// Product cache must be strictly compared to NULL, or else an empty cart will add dozens of queries
		if ($this->_products !== null && !$refresh)
		{
			// Return product row with specified ID if it exists
			if (is_int($id_product))
			{
				foreach ($this->_products as $product)
					if ($product['id_product'] == $id_product)
						return array($product);
				return array();
			}
			return $this->_products;
		}

		if (!$id_country)
			$id_country = Context::getContext()->country->id;

		// Build query
		$sql = new DbQuery();

		// Build SELECT
		$sql->select('cp.`id_product_attribute`, cp.`id_product`, cp.`quantity` AS cart_quantity, cp.id_shop, pl.`name`, p.`is_virtual`,
						pl.`description_short`, pl.`available_now`, pl.`available_later`, p.`id_product`, product_shop.`id_category_default`, p.`id_supplier`,
						p.`id_manufacturer`, product_shop.`on_sale`, product_shop.`ecotax`, product_shop.`additional_shipping_cost`, product_shop.`available_for_order`, product_shop.`price`, p.`weight`,
						stock.`quantity` quantity_available, p.`width`, p.`height`, p.`depth`, stock.`out_of_stock`,	p.`active`, p.`date_add`,
						p.`date_upd`, t.`id_tax`, tl.`name` AS tax, t.`rate`, IFNULL(stock.quantity, 0) as quantity, pl.`link_rewrite`, cl.`link_rewrite` AS category,
						CONCAT(cp.`id_product`, cp.`id_product_attribute`, cp.`id_address_delivery`) AS unique_id, cp.id_address_delivery,
						product_shop.`wholesale_price`, product_shop.advanced_stock_management');

		// Build FROM
		$sql->from('cart_product', 'cp');

		// Build JOIN
		$sql->leftJoin('product', 'p', 'p.`id_product` = cp.`id_product`');
		$sql->join(Shop::addSqlAssociation('product', 'p'));
		$sql->leftJoin('product_lang', 'pl', '
			p.`id_product` = pl.`id_product`
			AND pl.`id_lang` = '.(int)$this->id_lang.Shop::addSqlRestrictionOnLang('pl')
		);
		
		$sql->leftJoin('tax_rule', 'tr', '
			product_shop.`id_tax_rules_group` = tr.`id_tax_rules_group`
			AND tr.`id_country` = '.(int)$id_country.'
			AND tr.`id_state` = 0
			AND tr.`zipcode_from` = 0'
		);
		$sql->leftJoin('tax', 't', 't.`id_tax` = tr.`id_tax`');
		$sql->leftJoin('tax_lang', 'tl', '
			t.`id_tax` = tl.`id_tax`
			AND tl.`id_lang` = '.(int)$this->id_lang
		);

		$sql->leftJoin('category_lang', 'cl', '
			product_shop.`id_category_default` = cl.`id_category`
			AND cl.`id_lang` = '.(int)$this->id_lang.Shop::addSqlRestrictionOnLang('cl')
		);

		// @todo test if everything is ok, then refactorise call of this method
		Product::sqlStock('cp', 'cp', false, null, $sql);

		// Build WHERE clauses
		$sql->where('cp.`id_cart` = '.(int)$this->id);
		if ($id_product)
			$sql->where('cp.`id_product` = '.(int)$id_product);
		$sql->where('p.`id_product` IS NOT NULL');

		// Build GROUP BY
		$sql->groupBy('unique_id');

		// Build ORDER BY
		$sql->orderBy('p.id_product, cp.id_product_attribute, cp.date_add ASC');

		if (Customization::isFeatureActive())
		{
			$sql->select('cu.`id_customization`, cu.`quantity` AS customization_quantity');
			$sql->leftJoin('customization', 'cu', 'p.`id_product` = cu.`id_product`');
		}
		else
			$sql->select('0 AS customization_quantity');

		if (Combination::isFeatureActive())
		{
			$sql->select('
				product_attribute_shop.`price` AS price_attribute, product_attribute_shop.`ecotax` AS ecotax_attr,
				IF (IFNULL(pa.`reference`, \'\') = \'\', p.`reference`, pa.`reference`) AS reference,
				IF (IFNULL(pa.`supplier_reference`, \'\') = \'\', p.`supplier_reference`, pa.`supplier_reference`) AS supplier_reference,
				(p.`weight`+ pa.`weight`) weight_attribute,
				IF (IFNULL(pa.`ean13`, \'\') = \'\', p.`ean13`, pa.`ean13`) AS ean13,
				IF (IFNULL(pa.`upc`, \'\') = \'\', p.`upc`, pa.`upc`) AS upc,
				pai.`id_image` as pai_id_image, il.`legend` as pai_legend,
				IFNULL(product_attribute_shop.`minimal_quantity`, product_shop.`minimal_quantity`) as minimal_quantity
			');

			$sql->leftJoin('product_attribute', 'pa', 'pa.`id_product_attribute` = cp.`id_product_attribute`');
			$sql->join(Shop::addSqlAssociation('product_attribute', 'pa', false));
			$sql->leftJoin('product_attribute_image', 'pai', 'pai.`id_product_attribute` = pa.`id_product_attribute`');
			$sql->leftJoin('image_lang', 'il', 'il.id_image = pai.id_image AND il.id_lang = '.(int)$this->id_lang);
		}
		else
			$sql->select(
				'p.`reference` AS reference, p.`supplier_reference` AS supplier_reference, p.`ean13`,
				p.`upc` AS upc, product_shop.`minimal_quantity` AS minimal_quantity'
			);

		$result = Db::getInstance()->executeS($sql);

		// Reset the cache before the following return, or else an empty cart will add dozens of queries
		$products_ids = array();
		$pa_ids = array();
		foreach ($result as $row)
		{
			$products_ids[] = $row['id_product'];
			$pa_ids[] = $row['id_product_attribute'];
		}
		// Thus you can avoid one query per product, because there will be only one query for all the products of the cart
		Product::cacheProductsFeatures($products_ids);
		Cart::cacheSomeAttributesLists($pa_ids, $this->id_lang);

		$this->_products = array();
		if (empty($result))
			return array();

		foreach ($result as $row)
		{
			if (isset($row['ecotax_attr']) && $row['ecotax_attr'] > 0)
				$row['ecotax'] = (float)$row['ecotax_attr'];

			$row['stock_quantity'] = (int)$row['quantity'];
			// for compatibility with 1.2 themes
			$row['quantity'] = (int)$row['cart_quantity'];

			if (isset($row['id_product_attribute']) && (int)$row['id_product_attribute'] && isset($row['weight_attribute']))
				$row['weight'] = (float)$row['weight_attribute'];

			if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_invoice')
				$address_id = (int)$this->id_address_invoice;
			else
				$address_id = (int)$row['id_address_delivery'];
			if (!Address::addressExists($address_id))
				$address_id = null;

			if ($this->_taxCalculationMethod == PS_TAX_EXC)
			{
				$row['price'] = Product::getPriceStatic(
					(int)$row['id_product'],
					false,
					isset($row['id_product_attribute']) ? (int)$row['id_product_attribute'] : null,
					2,
					null,
					false,
					true,
					(int)$row['cart_quantity'],
					false,
					((int)$this->id_customer ? (int)$this->id_customer : null),
					(int)$this->id,
					((int)$address_id ? (int)$address_id : null),
					$specific_price_output
				); // Here taxes are computed only once the quantity has been applied to the product price

				$row['price_wt'] = Product::getPriceStatic(
					(int)$row['id_product'],
					true,
					isset($row['id_product_attribute']) ? (int)$row['id_product_attribute'] : null,
					2,
					null,
					false,
					true,
					(int)$row['cart_quantity'],
					false,
					((int)$this->id_customer ? (int)$this->id_customer : null),
					(int)$this->id,
					((int)$address_id ? (int)$address_id : null)
				);

				$tax_rate = Tax::getProductTaxRate((int)$row['id_product'], (int)$address_id);

				$row['total_wt'] = Tools::ps_round($row['price'] * (float)$row['cart_quantity'] * (1 + (float)$tax_rate / 100), 2);
				$row['total'] = $row['price'] * (int)$row['cart_quantity'];
			}
			else
			{
				$row['price'] = Product::getPriceStatic(
					(int)$row['id_product'],
					false,
					(int)$row['id_product_attribute'],
					6,
					null,
					false,
					true,
					$row['cart_quantity'],
					false,
					((int)$this->id_customer ? (int)$this->id_customer : null),
					(int)$this->id,
					((int)$address_id ? (int)$address_id : null),
					$specific_price_output
				);

				$row['price_wt'] = Product::getPriceStatic(
					(int)$row['id_product'],
					true,
					(int)$row['id_product_attribute'],
					2,
					null,
					false,
					true,
					$row['cart_quantity'],
					false,
					((int)$this->id_customer ? (int)$this->id_customer : null),
					(int)$this->id,
					((int)$address_id ? (int)$address_id : null)
				);

				// In case when you use QuantityDiscount, getPriceStatic() can be return more of 2 decimals
				$row['price_wt'] = Tools::ps_round($row['price_wt'], 2);
				$row['total_wt'] = $row['price_wt'] * (int)$row['cart_quantity'];
				$row['total'] = Tools::ps_round($row['price'] * (int)$row['cart_quantity'], 2);
			}

			if (!isset($row['pai_id_image']) || $row['pai_id_image'] == 0)
			{
				$row2 = Db::getInstance()->getRow('
					SELECT i.`id_image`, il.`legend`
					FROM `'._DB_PREFIX_.'image` i
					LEFT JOIN `'._DB_PREFIX_.'image_lang` il ON (i.`id_image` = il.`id_image` AND il.`id_lang` = '.(int)$this->id_lang.')
					WHERE i.`id_product` = '.(int)$row['id_product'].' AND i.`cover` = 1'
				);

				if (!$row2)
					$row2 = array('id_image' => false, 'legend' => false);
				else
					$row = array_merge($row, $row2);
			}
			else
			{
				$row['id_image'] = $row['pai_id_image'];
				$row['legend'] = $row['pai_legend'];
			}

			$row['reduction_applies'] = ($specific_price_output && (float)$specific_price_output['reduction']);
			$row['quantity_discount_applies'] = ($specific_price_output && $row['cart_quantity'] >= (int)$specific_price_output['from_quantity']);
			$row['id_image'] = Product::defineProductImage($row, $this->id_lang);
			$row['allow_oosp'] = Product::isAvailableWhenOutOfStock($row['out_of_stock']);
			$row['features'] = Product::getFeaturesStatic((int)$row['id_product']);

			if (array_key_exists($row['id_product_attribute'].'-'.$this->id_lang, self::$_attributesLists))
				$row = array_merge($row, self::$_attributesLists[$row['id_product_attribute'].'-'.$this->id_lang]);

			$this->_products[] = $row;
		}

		return $this->_products;
	}

	public static function cacheSomeAttributesLists($ipa_list, $id_lang)
	{
		if (!Combination::isFeatureActive())
			return;

		$pa_implode = array();

		foreach ($ipa_list as $id_product_attribute)
			if ((int)$id_product_attribute && !array_key_exists($id_product_attribute.'-'.$id_lang, self::$_attributesLists))
			{
				$pa_implode[] = (int)$id_product_attribute;
				self::$_attributesLists[(int)$id_product_attribute.'-'.$id_lang] = array('attributes' => '', 'attributes_small' => '');
			}

		if (!count($pa_implode))
			return;

		$result = Db::getInstance()->executeS('
			SELECT pac.`id_product_attribute`, agl.`public_name` AS public_group_name, al.`name` AS attribute_name
			FROM `'._DB_PREFIX_.'product_attribute_combination` pac
			LEFT JOIN `'._DB_PREFIX_.'attribute` a ON a.`id_attribute` = pac.`id_attribute`
			LEFT JOIN `'._DB_PREFIX_.'attribute_group` ag ON ag.`id_attribute_group` = a.`id_attribute_group`
			LEFT JOIN `'._DB_PREFIX_.'attribute_lang` al ON (
				a.`id_attribute` = al.`id_attribute`
				AND al.`id_lang` = '.(int)$id_lang.'
			)
			LEFT JOIN `'._DB_PREFIX_.'attribute_group_lang` agl ON (
				ag.`id_attribute_group` = agl.`id_attribute_group`
				AND agl.`id_lang` = '.(int)$id_lang.'
			)
			WHERE pac.`id_product_attribute` IN ('.implode($pa_implode, ',').')
			ORDER BY agl.`public_name` ASC'
		);

		foreach ($result as $row)
		{
			self::$_attributesLists[$row['id_product_attribute'].'-'.$id_lang]['attributes'] .= $row['public_group_name'].' : '.$row['attribute_name'].', ';
			self::$_attributesLists[$row['id_product_attribute'].'-'.$id_lang]['attributes_small'] .= $row['attribute_name'].', ';
		}

		foreach ($pa_implode as $id_product_attribute)
		{
			self::$_attributesLists[$id_product_attribute.'-'.$id_lang]['attributes'] = rtrim(
				self::$_attributesLists[$id_product_attribute.'-'.$id_lang]['attributes'],
				', '
			);

			self::$_attributesLists[$id_product_attribute.'-'.$id_lang]['attributes_small'] = rtrim(
				self::$_attributesLists[$id_product_attribute.'-'.$id_lang]['attributes_small'],
				', '
			);
		}
	}

	/**
	 * Return cart products quantity
	 *
	 * @result integer Products quantity
	 */
	public	function nbProducts()
	{
		if (!$this->id)
			return 0;

		return Cart::getNbProducts($this->id);
	}

	public static function getNbProducts($id)
	{
		// Must be strictly compared to NULL, or else an empty cart will bypass the cache and add dozens of queries
		if (isset(self::$_nbProducts[$id]) && self::$_nbProducts[$id] !== null)
			return self::$_nbProducts[$id];

		self::$_nbProducts[$id] = (int)Db::getInstance()->getValue('
			SELECT SUM(`quantity`)
			FROM `'._DB_PREFIX_.'cart_product`
			WHERE `id_cart` = '.(int)$id
		);

		return self::$_nbProducts[$id];
	}

	/**
	 * @deprecated 1.5.0, use Cart->addCartRule()
	 */
	public function addDiscount($id_cart_rule)
	{
		Tools::displayAsDeprecated();
		return $this->addCartRule($id_cart_rule);
	}

	public function addCartRule($id_cart_rule)
	{
		// You can't add a cart rule that does not exist
		$cartRule = new CartRule($id_cart_rule, Context::getContext()->language->id);
		if (!Validate::isLoadedObject($cartRule))
			return false;
		
		// Add the cart rule to the cart
		if (!Db::getInstance()->insert('cart_cart_rule', array(
			'id_cart_rule' => (int)$id_cart_rule,
			'id_cart' => (int)$this->id
		)))
			return false;
	
		Cache::clean('Cart::getCartRules'.$this->id);
	
		if ((int)$cartRule->gift_product)
			$this->updateQty(1, $cartRule->gift_product, $cartRule->gift_product_attribute, false, 0, 'up', null, false);

		return true;
	}

	public function containsProduct($id_product, $id_product_attribute = 0, $id_customization = false, $id_address_delivery = 0)
	{
		$sql = 'SELECT cp.`quantity` FROM `'._DB_PREFIX_.'cart_product` cp';

		if ($id_customization)
			$sql .= '
				LEFT JOIN `'._DB_PREFIX_.'customization` c ON (
					c.`id_product` = cp.`id_product`
					AND c.`id_product_attribute` = cp.`id_product_attribute`
				)';

		$sql .= '
			WHERE cp.`id_product` = '.(int)$id_product.'
			AND cp.`id_product_attribute` = '.(int)$id_product_attribute.'
			AND cp.`id_cart` = '.(int)$this->id.'
			AND cp.`id_address_delivery` = '.(int)$id_address_delivery;

		if ($id_customization)
			$sql .= ' AND c.`id_customization` = '.(int)$id_customization;

		return Db::getInstance()->getRow($sql);
	}

	/**
	 * Update product quantity
	 *
	 * @param integer $quantity Quantity to add (or substract)
	 * @param integer $id_product Product ID
	 * @param integer $id_product_attribute Attribute ID if needed
	 * @param string $operator Indicate if quantity must be increased or decreased
	 */
	public function updateQty($quantity, $id_product, $id_product_attribute = null, $id_customization = false,
		$id_address_delivery = 0, $operator = 'up', Shop $shop = null, $auto_add_cart_rule = true)
	{
		if (!$shop)
			$shop = Context::getContext()->shop;

		if (Context::getContext()->customer->id)
		{
			if ($id_address_delivery == 0 && (int)$this->id_address_delivery) // The $id_address_delivery is null, use the cart delivery address
				$id_address_delivery = $this->id_address_delivery;
			elseif ($id_address_delivery == 0) // The $id_address_delivery is null, get the default customer address
				$id_address_delivery = (int)Address::getFirstCustomerAddressId((int)Context::getContext()->customer->id);
			elseif (!Customer::customerHasAddress(Context::getContext()->customer->id, $id_address_delivery)) // The $id_address_delivery must be linked with customer
				$id_address_delivery = 0;
		}

		$quantity = (int)$quantity;
		$id_product = (int)$id_product;
		$id_product_attribute = (int)$id_product_attribute;
		$product = new Product($id_product, false, Configuration::get('PS_LANG_DEFAULT'), $shop->id);

		/* If we have a product combination, the minimal quantity is set with the one of this combination */
		if (!empty($id_product_attribute))
			$minimal_quantity = (int)Attribute::getAttributeMinimalQty($id_product_attribute);
		else
			$minimal_quantity = (int)$product->minimal_quantity;

		if (!Validate::isLoadedObject($product))
			die(Tools::displayError());

		if (isset(self::$_nbProducts[$this->id]))
			unset(self::$_nbProducts[$this->id]);

		if (isset(self::$_totalWeight[$this->id]))
			unset(self::$_totalWeight[$this->id]);

		if ((int)$quantity <= 0)
			return $this->deleteProduct($id_product, $id_product_attribute, (int)$id_customization);
		else if (!$product->available_for_order || Configuration::get('PS_CATALOG_MODE'))
			return false;
		else
		{
			/* Check if the product is already in the cart */
			$result = $this->containsProduct($id_product, $id_product_attribute, (int)$id_customization, (int)$id_address_delivery);

			/* Update quantity if product already exist */
			if ($result)
			{
				if ($operator == 'up')
				{
					$sql = 'SELECT stock.out_of_stock, IFNULL(stock.quantity, 0) as quantity
							FROM '._DB_PREFIX_.'product p
							'.Product::sqlStock('p', $id_product_attribute, true, $shop).'
							WHERE p.id_product = '.$id_product;

					$result2 = Db::getInstance()->getRow($sql);
					$product_qty = (int)$result2['quantity'];
					// Quantity for product pack
					if (Pack::isPack($id_product))
						$product_qty = Pack::getQuantity($id_product, $id_product_attribute);
					$new_qty = (int)$result['quantity'] + (int)$quantity;
					$qty = '+ '.(int)$quantity;

					if (!Product::isAvailableWhenOutOfStock((int)$result2['out_of_stock']))
						if ($new_qty > $product_qty)
							return false;
				}
				else if ($operator == 'down')
				{
					$qty = '- '.(int)$quantity;
					$new_qty = (int)$result['quantity'] - (int)$quantity;
					if ($new_qty < $minimal_quantity && $minimal_quantity > 1)
						return -1;
				}
				else
					return false;

				/* Delete product from cart */
				if ($new_qty <= 0)
					return $this->deleteProduct((int)$id_product, (int)$id_product_attribute, (int)$id_customization);
				else if ($new_qty < $minimal_quantity)
					return -1;
				else
					Db::getInstance()->execute('
						UPDATE `'._DB_PREFIX_.'cart_product`
						SET `quantity` = `quantity` '.$qty.', `date_add` = NOW()
						WHERE `id_product` = '.(int)$id_product.
						(!empty($id_product_attribute) ? ' AND `id_product_attribute` = '.(int)$id_product_attribute : '').'
						AND `id_cart` = '.(int)$this->id.' AND `id_address_delivery` = '.(int)$id_address_delivery.'
						LIMIT 1'
					);
			}
			/* Add product to the cart */
			elseif ($operator == 'up')
			{
				$sql = 'SELECT stock.out_of_stock, IFNULL(stock.quantity, 0) as quantity
						FROM '._DB_PREFIX_.'product p
						'.Product::sqlStock('p', $id_product_attribute, true, $shop).'
						WHERE p.id_product = '.$id_product;

				$result2 = Db::getInstance()->getRow($sql);

				// Quantity for product pack
				if (Pack::isPack($id_product))
					$result2['quantity'] = Pack::getQuantity($id_product, $id_product_attribute);

				if (!Product::isAvailableWhenOutOfStock((int)$result2['out_of_stock']))
					if ((int)$quantity > $result2['quantity'])
						return false;

				if ((int)$quantity < $minimal_quantity)
					return -1;

				$result_add = Db::getInstance()->insert('cart_product', array(
					'id_product' => 			(int)$id_product,
					'id_product_attribute' => 	(int)$id_product_attribute,
					'id_cart' => 				(int)$this->id,
					'id_address_delivery' => 	(int)$id_address_delivery,
					'id_shop' => 				$shop->id,
					'quantity' => 				(int)$quantity,
					'date_add' => 				date('Y-m-d H:i:s')
				));

				if (!$result_add)
					return false;
			}
		}

		// refresh cache of self::_products
		$this->_products = $this->getProducts(true);
		$this->update(true);
		$context = Context::getContext()->cloneContext();
		$context->cart = $this;
		if ($auto_add_cart_rule)
			CartRule::autoAddToCart($context);

		if ($product->customizable)
			return $this->_updateCustomizationQuantity((int)$quantity, (int)$id_customization, (int)$id_product, (int)$id_product_attribute, (int)$id_address_delivery, $operator);
		else
			return true;
	}

	/*
	** Customization management
	*/
	protected function _updateCustomizationQuantity($quantity, $id_customization, $id_product, $id_product_attribute, $id_address_delivery, $operator = 'up')
	{
		// Link customization to product combination when it is first added to cart
		if (empty($id_customization))
		{
			$customization = $this->getProductCustomization($id_product, null, true);
			foreach ($customization as $field)
			{
				if ($field['quantity'] == 0)
				{
					Db::getInstance()->execute('
					UPDATE `'._DB_PREFIX_.'customization`
					SET `quantity` = '.(int)$quantity.',
						`id_product_attribute` = '.(int)$id_product_attribute.',
						`id_address_delivery` = '.(int)$id_address_delivery.',
						`in_cart` = 1
					WHERE `id_customization` = '.(int)$field['id_customization']);
				}
			}
		}

		/* Deletion */
		if (!empty($id_customization) && (int)$quantity < 1)
			return $this->_deleteCustomization((int)$id_customization, (int)$id_product, (int)$id_product_attribute);

		/* Quantity update */
		if (!empty($id_customization))
		{
			$result = Db::getInstance()->getRow('SELECT `quantity` FROM `'._DB_PREFIX_.'customization` WHERE `id_customization` = '.(int)$id_customization);
			if ($result && Db::getInstance()->NumRows())
			{
				if ($operator == 'down' && (int)$result['quantity'] - (int)$quantity < 1)
					return Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'customization` WHERE `id_customization` = '.(int)$id_customization);

				return Db::getInstance()->execute('
					UPDATE `'._DB_PREFIX_.'customization`
					SET
						`quantity` = `quantity` '.($operator == 'up' ? '+ ' : '- ').(int)$quantity.',
						`id_address_delivery` = '.(int)$id_address_delivery.'
					WHERE `id_customization` = '.(int)$id_customization);
			}
			else
				Db::getInstance()->execute('
					UPDATE `'._DB_PREFIX_.'customization`
					SET `id_address_delivery` = '.(int)$id_address_delivery.'
					WHERE `id_customization` = '.(int)$id_customization);
		}
		// refresh cache of self::_products
		$this->_products = $this->getProducts(true);
		$this->update(true);
		return true;
	}

	/**
	 * Add customization item to database
	 *
	 * @param int $id_product
	 * @param int $id_product_attribute
	 * @param int $index
	 * @param int $type
	 * @param string $field
	 * @param int $quantity
	 * @return boolean success
	 */
	public function _addCustomization($id_product, $id_product_attribute, $index, $type, $field, $quantity)
	{
		$exising_customization = Db::getInstance()->executeS('
			SELECT cu.`id_customization`, cd.`index`, cd.`value`, cd.`type` FROM `'._DB_PREFIX_.'customization` cu
			LEFT JOIN `'._DB_PREFIX_.'customized_data` cd
			ON cu.`id_customization` = cd.`id_customization`
			WHERE cu.id_cart = '.(int)$this->id.'
			AND cu.id_product = '.(int)$id_product.'
			AND in_cart = 0'
		);

		if ($exising_customization)
		{
			// If the customization field is alreay filled, delete it
			foreach ($exising_customization as $customization)
			{
				if ($customization['type'] == $type && $customization['index'] == $index)
				{
					Db::getInstance()->execute('
						DELETE FROM `'._DB_PREFIX_.'customized_data`
						WHERE id_customization = '.(int)$customization['id_customization'].'
						AND type = '.(int)$customization['type'].'
						AND `index` = '.(int)$customization['index']);
					if ($type == Product::CUSTOMIZE_FILE)
					{
						@unlink(_PS_UPLOAD_DIR_.$customization['value']);
						@unlink(_PS_UPLOAD_DIR_.$customization['value'].'_small');
					}
					break;
				}
			}
			$id_customization = $exising_customization[0]['id_customization'];
		}
		else
		{
			Db::getInstance()->execute(
				'INSERT INTO `'._DB_PREFIX_.'customization` (`id_cart`, `id_product`, `id_product_attribute`, `quantity`)
				VALUES ('.(int)$this->id.', '.(int)$id_product.', '.(int)$id_product_attribute.', '.(int)$quantity.')'
			);
			$id_customization = Db::getInstance()->Insert_ID();
		}

		$query = 'INSERT INTO `'._DB_PREFIX_.'customized_data` (`id_customization`, `type`, `index`, `value`)
			VALUES ('.(int)$id_customization.', '.(int)$type.', '.(int)$index.', \''.pSql($field).'\')';

		if (!Db::getInstance()->execute($query))
			return false;
		return true;
	}

	/**
	 * Check if order has already been placed
	 *
	 * @return boolean result
	 */
	public function orderExists()
	{
		return (bool)Db::getInstance()->getValue('SELECT count(*) FROM `'._DB_PREFIX_.'orders` WHERE `id_cart` = '.(int)$this->id);
	}

	/**
	 * @deprecated 1.5.0, use Cart->removeCartRule()
	 */
	public function deleteDiscount($id_cart_rule)
	{
		Tools::displayAsDeprecated();
		return $this->removeCartRule($id_cart_rule);
	}

	public function removeCartRule($id_cart_rule)
	{
		Cache::clean('Cart::getCartRules'.$this->id);

		$result = Db::getInstance()->execute('
		DELETE FROM `'._DB_PREFIX_.'cart_cart_rule`
		WHERE `id_cart_rule` = '.(int)$id_cart_rule.'
		AND `id_cart` = '.(int)$this->id.'
		LIMIT 1');
		
		$cart_rule = new CartRule($id_cart_rule, Configuration::get('PS_LANG_DEFAULT'));
		if ((int)$cart_rule->gift_product)
			$this->updateQty(1, $cart_rule->gift_product, $cart_rule->gift_product_attribute, null, null, 'down', null, false);
		
		return $result;
	}

	/**
	 * Delete a product from the cart
	 *
	 * @param integer $id_product Product ID
	 * @param integer $id_product_attribute Attribute ID if needed
	 * @param integer $id_customization Customization id
	 * @return boolean result
	 */
	public function deleteProduct($id_product, $id_product_attribute = null, $id_customization = null, $id_address_delivery = 0)
	{
		if (isset(self::$_nbProducts[$this->id]))
			unset(self::$_nbProducts[$this->id]);

		if (isset(self::$_totalWeight[$this->id]))
			unset(self::$_totalWeight[$this->id]);

		if ((int)$id_customization)
		{
			$product_total_quantity = (int)Db::getInstance()->getValue(
				'SELECT `quantity`
				FROM `'._DB_PREFIX_.'cart_product`
				WHERE `id_product` = '.(int)$id_product.'
				AND `id_product_attribute` = '.(int)$id_product_attribute
			);

			$customization_quantity = (int)Db::getInstance()->getValue('
			SELECT `quantity`
			FROM `'._DB_PREFIX_.'customization`
			WHERE `id_cart` = '.(int)$this->id.'
			AND `id_product` = '.(int)$id_product.'
			AND `id_product_attribute` = '.(int)$id_product_attribute.'
			'.((int)$id_address_delivery ? 'AND `id_address_delivery` = '.(int)$id_address_delivery : ''));

			if (!$this->_deleteCustomization((int)$id_customization, (int)$id_product, (int)$id_product_attribute, $id_address_delivery))
				return false;

			// refresh cache of self::_products
			$this->_products = $this->getProducts(true);
			return ($customization_quantity == $product_total_quantity && $this->deleteProduct((int)$id_product, $id_product_attribute, $id_address_delivery));
		}

		/* Get customization quantity */
		$result = Db::getInstance()->getRow('
			SELECT SUM(`quantity`) AS \'quantity\'
			FROM `'._DB_PREFIX_.'customization`
			WHERE `id_cart` = '.(int)$this->id.'
			AND `id_product` = '.(int)$id_product.'
			AND `id_product_attribute` = '.(int)$id_product_attribute
		);

		if ($result === false)
			return false;

		/* If the product still possesses customization it does not have to be deleted */
		if (Db::getInstance()->NumRows() && (int)$result['quantity'])
			return Db::getInstance()->execute('
				UPDATE `'._DB_PREFIX_.'cart_product`
				SET `quantity` = '.(int)$result['quantity'].'
				WHERE `id_cart` = '.(int)$this->id.'
				AND `id_product` = '.(int)$id_product.
				($id_product_attribute != null ? ' AND `id_product_attribute` = '.(int)$id_product_attribute : '')
			);

		/* Product deletion */
		$result = Db::getInstance()->execute('
		DELETE FROM `'._DB_PREFIX_.'cart_product`
		WHERE `id_product` = '.(int)$id_product.'
		'.(!is_null($id_product_attribute) ? ' AND `id_product_attribute` = '.(int)$id_product_attribute : '').'
		AND `id_cart` = '.(int)$this->id.'
		'.((int)$id_address_delivery ? 'AND `id_address_delivery` = '.(int)$id_address_delivery : ''));

		if ($result)
		{
			// refresh cache of self::_products
			$this->_products = $this->getProducts(true);
			$return = $this->update(true);
			
			// If there isn't any product left, remove all the cart rules associated
			if (!$this->nbProducts())
				$result = Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'cart_cart_rule` WHERE `id_cart` = '.(int)$this->id);
				
			return $return;
		}

		return false;
	}

	/**
	 * Delete a customization from the cart. If customization is a Picture,
	 * then the image is also deleted
	 *
	 * @param integer $id_customization
	 * @return boolean result
	 */
	protected function _deleteCustomization($id_customization, $id_product, $id_product_attribute, $id_address_delivery = 0)
	{
		$result = true;
		$customization = Db::getInstance()->getRow('SELECT *
			FROM `'._DB_PREFIX_.'customization`
			WHERE `id_customization` = '.(int)$id_customization);

		if ($customization)
		{
			$cust_data = Db::getInstance()->getRow('SELECT *
				FROM `'._DB_PREFIX_.'customized_data`
				WHERE `id_customization` = '.(int)$id_customization);

			// Delete customization picture if necessary
			if (isset($cust_data['type']) && $cust_data['type'] == 0)
				$result &= (@unlink(_PS_UPLOAD_DIR_.$cust_data['value']) && @unlink(_PS_UPLOAD_DIR_.$cust_data['value'].'_small'));

			$result &= Db::getInstance()->execute(
				'DELETE FROM `'._DB_PREFIX_.'customized_data`
				WHERE `id_customization` = '.(int)$id_customization
			);

			if ($result)
				$result &= Db::getInstance()->execute(
					'UPDATE `'._DB_PREFIX_.'cart_product`
					SET `quantity` = `quantity` - '.(int)$customization['quantity'].'
					WHERE `id_cart` = '.(int)$this->id.'
					AND `id_product` = '.(int)$id_product.
					((int)$id_product_attribute ? ' AND `id_product_attribute` = '.(int)$id_product_attribute : '').'
					AND `id_address_delivery` = '.(int)$id_address_delivery
				);

			if (!$result)
				return false;

			return Db::getInstance()->execute(
				'DELETE FROM `'._DB_PREFIX_.'customization`
				WHERE `id_customization` = '.(int)$id_customization
			);
		}

		return true;
	}

	public static function getTotalCart($id_cart, $use_tax_display = false)
	{
		$cart = new Cart($id_cart);
		if (!Validate::isLoadedObject($cart))
			die(Tools::displayError());

		$with_taxes = $use_tax_display ? $cart->_taxCalculationMethod != PS_TAX_EXC : true;
		return Tools::displayPrice($cart->getOrderTotal($with_taxes), Currency::getCurrencyInstance((int)$cart->id_currency), false);
	}


	public static function getOrderTotalUsingTaxCalculationMethod($id_cart)
	{
		return Cart::getTotalCart($id_cart, true);
	}

	/**
	* This function returns the total cart amount
	*
	* Possible values for $type:
	* Cart::ONLY_PRODUCTS
	* Cart::ONLY_DISCOUNTS
	* Cart::BOTH
	* Cart::BOTH_WITHOUT_SHIPPING
	* Cart::ONLY_SHIPPING
	* Cart::ONLY_WRAPPING
	* Cart::ONLY_PRODUCTS_WITHOUT_SHIPPING
	* Cart::ONLY_PHYSICAL_PRODUCTS_WITHOUT_SHIPPING
	*
	* @param boolean $withTaxes With or without taxes
	* @param integer $type Total type
	* @return float Order total
	*/
	public function getOrderTotal($with_taxes = true, $type = Cart::BOTH, $products = null, $id_carrier = null)
	{
		if (!$this->id)
			return 0;

		$type = (int)$type;
		$array_type = array(
			Cart::ONLY_PRODUCTS,
			Cart::ONLY_DISCOUNTS,
			Cart::BOTH,
			Cart::BOTH_WITHOUT_SHIPPING,
			Cart::ONLY_SHIPPING,
			Cart::ONLY_WRAPPING,
			Cart::ONLY_PRODUCTS_WITHOUT_SHIPPING,
			Cart::ONLY_PHYSICAL_PRODUCTS_WITHOUT_SHIPPING,
		);
		
		// Define virtual context to prevent case where the cart is not the in the global context
		$virtual_context = Context::getContext()->cloneContext();
		$virtual_context->cart = $this;

		if (!in_array($type, $array_type))
			die(Tools::displayError());

		$with_shipping = in_array($type, array(Cart::BOTH, Cart::ONLY_SHIPPING));
		
		// if cart rules are not used
		if ($type == Cart::ONLY_DISCOUNTS && !CartRule::isFeatureActive())
			return 0;

		// no shipping cost if is a cart with only virtuals products
		$virtual = $this->isVirtualCart();
		if ($virtual && $type == Cart::ONLY_SHIPPING)
			return 0;

		if ($virtual && $type == Cart::BOTH)
			$type = Cart::BOTH_WITHOUT_SHIPPING;

		if ($with_shipping)
		{
			if (is_null($products) && is_null($id_carrier))
				$shipping_fees = $this->getTotalShippingCost(null, (boolean)$with_taxes);
			else
				$shipping_fees = $this->getPackageShippingCost($id_carrier, (int)$with_taxes, null, $products);
		}
		else
			$shipping_fees = 0;

		if ($type == Cart::ONLY_PRODUCTS_WITHOUT_SHIPPING)
			$type = Cart::ONLY_PRODUCTS;

		if (is_null($products))
			$products = $this->getProducts();
	
		if ($type == Cart::ONLY_PHYSICAL_PRODUCTS_WITHOUT_SHIPPING)
		{
			foreach ($products as $key => $product)
				if ($product['is_virtual'])
					unset($products[$key]);
			$type = Cart::ONLY_PRODUCTS;
		}

		$order_total = 0;
		if (Tax::excludeTaxeOption())
			$with_taxes = false;

		foreach ($products as $product) // products refer to the cart details
		{
			if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_invoice')
				$address_id = (int)$this->id_address_invoice;
			else
				$address_id = (int)$product['id_address_delivery']; // Get delivery address of the product from the cart
			if (!Address::addressExists($address_id))
				$address_id = null;
			
			if ($this->_taxCalculationMethod == PS_TAX_EXC)
			{
				// Here taxes are computed only once the quantity has been applied to the product price
				$price = Product::getPriceStatic(
					(int)$product['id_product'],
					false,
					(int)$product['id_product_attribute'],
					2,
					null,
					false,
					true,
					$product['cart_quantity'],
					false,
					(int)$this->id_customer ? (int)$this->id_customer : null,
					(int)$this->id,
					$address_id
				);

				$total_ecotax = $product['ecotax'] * (int)$product['cart_quantity'];
				$total_price = $price * (int)$product['cart_quantity'];

				if ($with_taxes)
				{
					$product_tax_rate = (float)Tax::getProductTaxRate((int)$product['id_product'], (int)$address_id);
					$product_eco_tax_rate = Tax::getProductEcotaxRate((int)$address_id);

					$total_price = ($total_price - $total_ecotax) * (1 + $product_tax_rate / 100);
					$total_ecotax = $total_ecotax * (1 + $product_eco_tax_rate / 100);
					$total_price = Tools::ps_round($total_price + $total_ecotax, 2);
				}
			}
			else
			{
				$price = Product::getPriceStatic(
					(int)$product['id_product'],
					true,
					(int)$product['id_product_attribute'],
					2,
					null,
					false,
					true,
					$product['cart_quantity'],
					false,
					((int)$this->id_customer ? (int)$this->id_customer : null),
					(int)$this->id,
					((int)$address_id ? (int)$address_id : null)
				);

				$total_price = Tools::ps_round($price, 2) * (int)$product['cart_quantity'];

				if (!$with_taxes)
				{
					$product_tax_rate = (float)Tax::getProductTaxRate((int)$product['id_product'], (int)$address_id);
					$total_price = Tools::ps_round($total_price / (1 + ($product_tax_rate / 100)), 2);
				}
			}
			$order_total += $total_price;
		}

		$order_total_products = $order_total;

		if ($type == Cart::ONLY_DISCOUNTS)
			$order_total = 0;

		// Wrapping Fees
		$wrapping_fees = 0;
		if ($this->gift)
		{
			$wrapping_fees = (float)Configuration::get('PS_GIFT_WRAPPING_PRICE');
			if ($with_taxes)
			{
				$wrapping_fees_tax = new Tax(Configuration::get('PS_GIFT_WRAPPING_TAX'));
				$wrapping_fees *= 1 + ((float)$wrapping_fees_tax->rate / 100);
			}
			$wrapping_fees = Tools::convertPrice(Tools::ps_round($wrapping_fees, 2), Currency::getCurrencyInstance((int)$this->id_currency));
		}

		$order_total_discount = 0;
		if ($type != Cart::ONLY_PRODUCTS && CartRule::isFeatureActive())
		{			
			if ($with_shipping)
				$cart_rules = $this->getCartRules(CartRule::FILTER_ACTION_ALL);
			else
			{
				$cart_rules = $this->getCartRules(CartRule::FILTER_ACTION_REDUCTION);
				// Cart Rules array are merged manually in order to avoid doubles
				foreach ($this->getCartRules(CartRule::FILTER_ACTION_GIFT) as $tmp_cart_rule)
				{
					$flag = false;
					foreach ($cart_rules as $cart_rule)
						if ($tmp_cart_rule['id_cart_rule'] == $cart_rule['id_cart_rule'])
							$flag = true;
					if (!$flag)
						$cart_rules[] = $tmp_cart_rule;
				}
			}				
	
			foreach ($cart_rules as $cart_rule)
				if ($with_shipping)
					$order_total_discount += Tools::ps_round($cart_rule['obj']->getContextualValue($with_taxes, $virtual_context, CartRule::FILTER_ACTION_ALL), 2);
				else
				{
					$order_total_discount += Tools::ps_round($cart_rule['obj']->getContextualValue($with_taxes, $virtual_context, CartRule::FILTER_ACTION_REDUCTION), 2);
					$order_total_discount += Tools::ps_round($cart_rule['obj']->getContextualValue($with_taxes, $virtual_context, CartRule::FILTER_ACTION_GIFT), 2);
				}

			$order_total_discount = min(Tools::ps_round($order_total_discount, 2), $wrapping_fees + $order_total_products + $shipping_fees);
			$order_total -= $order_total_discount;
		}

		if ($type == Cart::ONLY_SHIPPING)
			return $shipping_fees;

		if ($type == Cart::ONLY_WRAPPING)
			return $wrapping_fees;

		if ($type == Cart::BOTH)
			$order_total += $shipping_fees + $wrapping_fees;

		if ($order_total < 0 && $type != Cart::ONLY_DISCOUNTS)
			return 0;

		if ($type == Cart::ONLY_DISCOUNTS)
			return $order_total_discount;

		return Tools::ps_round((float)$order_total, 2);
	}

	/**
	 * Get products grouped by package and by addresses to be sent individualy (one package = one shipping cost).
	 *
	 * @return array array(
	 *                   0 => array( // First address
	 *                       0 => array(  // First package
	 *                           'product_list' => array(...),
	 *                           'carrier_list' => array(...),
	 *                           'id_warehouse' => array(...),
	 *                       ),
	 *                   ),
	 *               );
	 * @todo Add avaibility check
	 */
	public function getPackageList($flush = false)
	{
		static $cache = false;
		if ($cache !== false && !$flush)
			return $cache;

		$product_list = $this->getProducts();
		// Step 1 : Get product informations (warehouse_list and carrier_list), count warehouse
		// Determine the best warehouse to determine the packages
		// For that we count the number of time we can use a warehouse for a specific delivery address
		$warehouse_count_by_address = array();
		$warehouse_carrier_list = array();

		$stock_management_active = Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT');

		foreach ($product_list as &$product)
		{
			if ((int)$product['id_address_delivery'] == 0)
				$product['id_address_delivery'] = (int)$this->id_address_delivery;

			if (!isset($warehouse_count_by_address[$product['id_address_delivery']]))
				$warehouse_count_by_address[$product['id_address_delivery']] = array();

			$product['warehouse_list'] = array();

			if ($stock_management_active &&
				((int)$product['advanced_stock_management'] == 1 || Pack::usesAdvancedStockManagement((int)$product['id_product'])))
			{
				$warehouse_list = Warehouse::getProductWarehouseList($product['id_product'], $product['id_product_attribute'], $this->id_shop);
				if (count($warehouse_list) == 0)
					$warehouse_list = Warehouse::getProductWarehouseList($product['id_product'], $product['id_product_attribute']);
				// Does the product is in stock ?
				// If yes, get only warehouse where the product is in stock

				$warehouse_in_stock = array();
				$manager = StockManagerFactory::getManager();

				foreach ($warehouse_list as $key => $warehouse)
				{
					$product_real_quantities = $manager->getProductRealQuantities(
						$product['id_product'],
						$product['id_product_attribute'],
						array($warehouse['id_warehouse']),
						true
					);

					if ($product_real_quantities > 0 || Pack::isPack((int)$product['id_product']))
						$warehouse_in_stock[] = $warehouse;
				}

				if (!empty($warehouse_in_stock))
				{
					$warehouse_list = $warehouse_in_stock;
					$product['in_stock'] = true;
				}
				else
					$product['in_stock'] = false;
			}
			else
			{
				//simulate default warehouse
				$warehouse_list = array(0);
				$product['in_stock'] = StockAvailable::getQuantityAvailableByProduct($product['id_product'], $product['id_product_attribute']) > 0;
			}

			foreach ($warehouse_list as $warehouse)
			{
				if (!isset($warehouse_carrier_list[$warehouse['id_warehouse']]))
				{
					$warehouse_object = new Warehouse($warehouse['id_warehouse']);
					$warehouse_carrier_list[$warehouse['id_warehouse']] = $warehouse_object->getCarriers();
				}

				$product['warehouse_list'][] = $warehouse['id_warehouse'];
				if (!isset($warehouse_count_by_address[$product['id_address_delivery']][$warehouse['id_warehouse']]))
					$warehouse_count_by_address[$product['id_address_delivery']][$warehouse['id_warehouse']] = 0;

				$warehouse_count_by_address[$product['id_address_delivery']][$warehouse['id_warehouse']]++;
			}
		}

		arsort($warehouse_count_by_address);

		// Step 2 : Group product by warehouse
		$grouped_by_warehouse = array();
		foreach ($product_list as &$product)
		{
			if (!isset($grouped_by_warehouse[$product['id_address_delivery']]))
				$grouped_by_warehouse[$product['id_address_delivery']] = array(
					'in_stock' => array(),
					'out_of_stock' => array(),
				);

			// Determine the warehouse to use for this product in order to reduce the number of package
			$id_warehouse = 0;
			foreach ($warehouse_count_by_address[$product['id_address_delivery']] as $id_warehouse => $val)
				if (in_array((int)$id_warehouse, $product['warehouse_list']))
					break;

			$id_warehouse = (int)$id_warehouse;

			if (!isset($grouped_by_warehouse[$product['id_address_delivery']]['in_stock'][$id_warehouse]))
			{
				$grouped_by_warehouse[$product['id_address_delivery']]['in_stock'][$id_warehouse] = array();
				$grouped_by_warehouse[$product['id_address_delivery']]['out_of_stock'][$id_warehouse] = array();
			}

			if (!$this->allow_seperated_package)
				$key = 'in_stock';
			else
				$key = (!$product['out_of_stock']) ? 'in_stock' : 'out_of_stock';

			$product['carrier_list'] = Carrier::getAvailableCarrierList(new Product($product['id_product']), $id_warehouse, $product['id_address_delivery'], null, $this);

			if (empty($product['carrier_list']))
				$product['carrier_list'] = array(0);

			$grouped_by_warehouse[$product['id_address_delivery']][$key][$id_warehouse][] = $product;
		}

		// Step 3 : grouped product from grouped_by_warehouse by available carriers
		$grouped_by_carriers = array();
		foreach ($grouped_by_warehouse as $id_address_delivery => $products_in_stock_list)
		{
			if (!isset($grouped_by_carriers[$id_address_delivery]))
				$grouped_by_carriers[$id_address_delivery] = array(
					'in_stock' => array(),
					'out_of_stock' => array(),
				);

			foreach ($products_in_stock_list as $key => $warehouse_list)
			{
				if (!isset($grouped_by_carriers[$id_address_delivery][$key]))
					$grouped_by_carriers[$id_address_delivery][$key] = array();
				foreach ($warehouse_list as $id_warehouse => $product_list)
				{
					if (!isset($grouped_by_carriers[$id_address_delivery][$key][$id_warehouse]))
						$grouped_by_carriers[$id_address_delivery][$key][$id_warehouse] = array();

					foreach ($product_list as $product)
					{
						$package_carriers_key = implode(',', $product['carrier_list']);

						if (!isset($grouped_by_carriers[$id_address_delivery][$key][$id_warehouse][$package_carriers_key]))
							$grouped_by_carriers[$id_address_delivery][$key][$id_warehouse][$package_carriers_key] = array(
								'product_list' => array(),
								'carrier_list' => $product['carrier_list']
							);

						$grouped_by_carriers[$id_address_delivery][$key][$id_warehouse][$package_carriers_key]['product_list'][] = $product;
					}
				}
			}
		}

		$package_list = array();
		// Step 4 : merge product from grouped_by_carriers into $package to minimize the number of package
		foreach ($grouped_by_carriers as $id_address_delivery => $products_in_stock_list)
		{
			if (!isset($package_list[$id_address_delivery]))
				$package_list[$id_address_delivery] = array(
					'in_stock' => array(),
					'out_of_stock' => array(),
				);

			foreach ($products_in_stock_list as $key => $warehouse_list)
			{
				if (!isset($package_list[$id_address_delivery][$key]))
					$package_list[$id_address_delivery][$key] = array();
				// Count occurance of each carriers to minimize the number of packages
				$carrier_count = array();
				foreach ($warehouse_list as $id_warehouse => $products_grouped_by_carriers)
				{
					foreach ($products_grouped_by_carriers as $data)
					{
						foreach ($data['carrier_list'] as $id_carrier)
						{
							if (!isset($carrier_count[$id_carrier]))
								$carrier_count[$id_carrier] = 0;
							$carrier_count[$id_carrier]++;
						}
					}
				}
				arsort($carrier_count);

				foreach ($warehouse_list as $id_warehouse => $products_grouped_by_carriers)
				{
					if (!isset($package_list[$id_address_delivery][$key][$id_warehouse]))
						$package_list[$id_address_delivery][$key][$id_warehouse] = array();
					foreach ($products_grouped_by_carriers as $data)
					{
						foreach ($carrier_count as $id_carrier => $rate)
						{
							if (in_array($id_carrier, $data['carrier_list']))
							{
								if (!isset($package_list[$id_address_delivery][$key][$id_warehouse][$id_carrier]))
									$package_list[$id_address_delivery][$key][$id_warehouse][$id_carrier] = array(
										'carrier_list' => $data['carrier_list'],
										'product_list' => array(),
									);
								$package_list[$id_address_delivery][$key][$id_warehouse][$id_carrier]['carrier_list'] =
									array_intersect($package_list[$id_address_delivery][$key][$id_warehouse][$id_carrier]['carrier_list'], $data['carrier_list']);

								$package_list[$id_address_delivery][$key][$id_warehouse][$id_carrier]['product_list'] =
									array_merge($package_list[$id_address_delivery][$key][$id_warehouse][$id_carrier]['product_list'], $data['product_list']);

								break;
							}
						}
					}
				}
			}
		}

		// Step 5 : Reduce depth of $package_list
		$final_package_list = array();
		foreach ($package_list as $id_address_delivery => $products_in_stock_list)
		{
			if (!isset($final_package_list[$id_address_delivery]))
				$final_package_list[$id_address_delivery] = array();

			foreach ($products_in_stock_list as $key => $warehouse_list)
				foreach ($warehouse_list as $id_warehouse => $products_grouped_by_carriers)
					foreach ($products_grouped_by_carriers as $data)
					{
						$final_package_list[$id_address_delivery][] = array(
							'product_list' => $data['product_list'],
							'carrier_list' => $data['carrier_list'],
							'id_warehouse' => $id_warehouse,
						);
					}
		}

		$cache = $final_package_list;
		return $final_package_list;
	}

	/**
	 * Get all deliveries options available for the current cart
	 * @param Country $default_country
	 * @param boolean $flush Force flushing cache
	 *
	 * @return array array(
	 *                   0 => array( // First address
	 *                       '12,' => array(  // First delivery option available for this address
	 *                           carrier_list => array(
	 *                               12 => array( // First carrier for this option
	 *                                   'instance' => Carrier Object,
	 *                                   'logo' => <url to the carriers logo>,
	 *                                   'price_with_tax' => 12.4,
	 *                                   'price_without_tax' => 12.4,
	 *                                   'package_list' => array(
	 *                                       1,
	 *                                       3,
	 *                                   ),
	 *                               ),
	 *                           ),
	 *                           is_best_grade => true, // Does this option have the biggest grade (quick shipping) for this shipping address
	 *                           is_best_price => true, // Does this option have the lower price for this shipping address
	 *                           unique_carrier => true, // Does this option use a unique carrier
	 *                           total_price_with_tax => 12.5,
	 *                           total_price_without_tax => 12.5,
	 *                       ),
	 *                   ),
	 *               );
	 *               If there are no carriers available for an address, return an empty  array
	 */
	public function getDeliveryOptionList(Country $default_country = null, $flush = false)
	{
		static $cache = false;
		if ($cache !== false && !$flush)
			return $cache;

		$delivery_option_list = array();
		$carriers_price = array();
		$carrier_collection = array();
		$package_list = $this->getPackageList();

		foreach ($package_list as $id_address => $packages)
		{
			$delivery_option_list[$id_address] = array();
			$carriers_price[$id_address] = array();
			$common_carriers = null;
			$best_price_carriers = array();
			$best_grade_carriers = array();
			$carriers_instance = array();
			
			if ($id_address)
			{
				$address = new Address($id_address);
				$country = new Country($address->id_country);
			} else
				$country = $default_country;

			foreach ($packages as $id_package => $package)
			{
				// No carriers available
				if (count($package['carrier_list']) == 1 && current($package['carrier_list']) == 0)
					return array();

				$carriers_price[$id_address][$id_package] = array();

				if (is_null($common_carriers))
					$common_carriers = $package['carrier_list'];
				else
					$common_carriers = array_intersect($common_carriers, $package['carrier_list']);

				$best_price = null;
				$best_price_carrier = null;
				$best_grade = null;
				$best_grade_carrier = null;

				foreach ($package['carrier_list'] as $id_carrier)
				{
					if (!isset($carriers_instance[$id_carrier]))
						$carriers_instance[$id_carrier] = new Carrier($id_carrier);
					$price_with_tax = $this->getPackageShippingCost($id_carrier, true, $country, $package['product_list']);
					$price_without_tax = $this->getPackageShippingCost($id_carrier, false, $country, $package['product_list']);
					if (is_null($best_price) || $price_with_tax < $best_price)
					{
						$best_price = $price_with_tax;
						$best_price_carrier = $id_carrier;
					}
					$carriers_price[$id_address][$id_package][$id_carrier] = array(
						'without_tax' => $price_without_tax,
						'with_tax' => $price_with_tax);

					$grade = $carriers_instance[$id_carrier]->grade;
					if (is_null($best_grade) || $grade > $best_grade)
					{
						$best_grade = $grade;
						$best_grade_carrier = $id_carrier;
					}
				}

				$best_price_carriers[$id_package] = $best_price_carrier;
				$best_grade_carriers[$id_package] = $best_grade_carrier;
			}

			$best_price_carrier = array();
			$key = '';

			foreach ($best_price_carriers as $id_package => $id_carrier)
			{
				$key .= $id_carrier.',';
				if (!isset($best_price_carrier[$id_carrier]))
					$best_price_carrier[$id_carrier] = array(
						'price_with_tax' => 0,
						'price_without_tax' => 0,
						'package_list' => array()
					);
				$best_price_carrier[$id_carrier]['price_with_tax'] += $carriers_price[$id_address][$id_package][$id_carrier]['with_tax'];
				$best_price_carrier[$id_carrier]['price_without_tax'] += $carriers_price[$id_address][$id_package][$id_carrier]['without_tax'];
				$best_price_carrier[$id_carrier]['package_list'][] = $id_package;
				$best_price_carrier[$id_carrier]['instance'] = $carriers_instance[$id_carrier];
			}

			$delivery_option_list[$id_address][$key] = array(
				'carrier_list' => $best_price_carrier,
				'is_best_price' => true,
				'is_best_grade' => false,
				'unique_carrier' => (count($best_price_carrier) <= 1)
			);

			$best_grade_carrier = array();
			$key = '';

			foreach ($best_grade_carriers as $id_package => $id_carrier)
			{
				$key .= $id_carrier.',';
				if (!isset($best_grade_carrier[$id_carrier]))
					$best_grade_carrier[$id_carrier] = array(
						'price_with_tax' => 0,
						'price_without_tax' => 0,
						'package_list' => array()
					);
				$best_grade_carrier[$id_carrier]['price_with_tax'] += $carriers_price[$id_address][$id_package][$id_carrier]['with_tax'];
				$best_grade_carrier[$id_carrier]['price_without_tax'] += $carriers_price[$id_address][$id_package][$id_carrier]['without_tax'];
				$best_grade_carrier[$id_carrier]['package_list'][] = $id_package;
				$best_grade_carrier[$id_carrier]['instance'] = $carriers_instance[$id_carrier];
			}

			if (!isset($delivery_option_list[$id_address][$key]))
				$delivery_option_list[$id_address][$key] = array(
					'carrier_list' => $best_grade_carrier,
					'is_best_price' => false,
					'unique_carrier' => (count($best_grade_carrier) <= 1)
				);

			$delivery_option_list[$id_address][$key]['is_best_grade'] = true;

			foreach ($common_carriers as $id_carrier)
			{
				$price = 0;
				$key = '';
				$package_list = array();
				$total_price_with_tax = 0;
				$total_price_without_tax = 0;
				$price_with_tax = 0;
				$price_without_tax = 0;

				foreach ($packages as $id_package => $package)
				{
					$key .= $id_carrier.',';
					$price_with_tax += $carriers_price[$id_address][$id_package][$id_carrier]['with_tax'];
					$price_without_tax += $carriers_price[$id_address][$id_package][$id_carrier]['without_tax'];
					$package_list[] = $id_package;
				}

				if (!isset($delivery_option_list[$id_address][$key]))
					$delivery_option_list[$id_address][$key] = array(
						'is_best_price' => false,
						'is_best_grade' => false,
						'unique_carrier' => true,
						'carrier_list' => array(
							$id_carrier => array(
								'price_with_tax' => $price_with_tax,
								'price_without_tax' => $price_without_tax,
								'instance' => $carriers_instance[$id_carrier],
								'package_list' => $package_list
							)
						)
					);
				else
					$delivery_option_list[$id_address][$key]['unique_carrier'] = (count($delivery_option_list[$id_address][$key]['carrier_list']) <= 1);
			}

			foreach ($delivery_option_list as $id_address => $delivery_option)
				foreach ($delivery_option as $key => $value)
				{
					$total_price_with_tax = 0;
					$total_price_without_tax = 0;
					foreach ($value['carrier_list'] as $id_carrier => $data)
					{
						$total_price_with_tax += $data['price_with_tax'];
						$total_price_without_tax += $data['price_without_tax'];

						if (!isset($carrier_collection[$id_carrier]))
							$carrier_collection[$id_carrier] = new Carrier($id_carrier);
						$delivery_option_list[$id_address][$key]['carrier_list'][$id_carrier]['instance'] = $carrier_collection[$id_carrier];

						if (file_exists(_PS_SHIP_IMG_DIR_.$id_carrier.'.jpg'))
							$delivery_option_list[$id_address][$key]['carrier_list'][$id_carrier]['logo'] = _THEME_SHIP_DIR_.$id_carrier.'.jpg';
						else
							$delivery_option_list[$id_address][$key]['carrier_list'][$id_carrier]['logo'] = false;
					}
					$delivery_option_list[$id_address][$key]['total_price_with_tax'] = $total_price_with_tax;
					$delivery_option_list[$id_address][$key]['total_price_without_tax'] = $total_price_without_tax;
				}
		}

		$cache = $delivery_option_list;
		return $delivery_option_list;
	}
	
	public function carrierIsSelected($id_carrier, $id_address)
	{
		$delivery_option = $this->getDeliveryOption();
		$delivery_option_list = $this->getDeliveryOptionList();
		
		if (!isset($delivery_option[$id_address]))
			return false;
			
		if (!isset($delivery_option_list[$id_address][$delivery_option[$id_address]]))
			return false;
		
		if (!in_array($id_carrier, array_keys($delivery_option_list[$id_address][$delivery_option[$id_address]]['carrier_list'])))
			return false;
			
		return true;
	}

	/**
	 * Get all deliveries options available for the current cart formated like Carriers::getCarriersForOrder
	 * This method was wrote for retrocompatibility with 1.4 theme
	 * New theme need to use Cart::getDeliveryOptionList() to generate carriers option in the checkout process
	 *
	 * @since 1.5.0
	 *
	 * @param Country $default_country
	 * @param boolean $flush Force flushing cache
	 *
	 */
	public function simulateCarriersOutput(Country $default_country = null, $flush = false)
	{
		static $cache = false;
		if ($cache !== false && !$flush)
			return $cache;

		$delivery_option_list = $this->getDeliveryOptionList($default_country, $flush);

		// This method cannot work if there is multiple address delivery
		if (count($delivery_option_list) > 1 || empty($delivery_option_list))
			return array();

		$carriers = array();
		foreach (reset($delivery_option_list) as $key => $option)
		{
			$price = $option['total_price_with_tax'];
			$price_tax_exc = $option['total_price_without_tax'];

			if ($option['unique_carrier'])
			{
				$carrier = reset($option['carrier_list']);
				$name = $carrier['instance']->name;
				$img = $carrier['logo'];
				$delay = $carrier['instance']->delay;
				$delay = isset($delay[Context::getContext()->language->id]) ? $delay[Context::getContext()->language->id] : $delay[(int)Configuration::get('PS_LANG_DEFAULT')];
			}
			else
			{
				$nameList = array();
				foreach ($option['carrier_list'] as $carrier)
					$nameList[] = $carrier['instance']->name;
				$name = join(' -', $nameList);
				$img = ''; // No images if multiple carriers
				$delay = '';
			}
			$carriers[] = array(
				'name' => $name,
				'img' => $img,
				'delay' => $delay,
				'price' => $price,
				'price_tax_exc' => $price_tax_exc,
				'id_carrier' => Cart::intifier($key), // Need to translate to an integer for retrocompatibility reason, in 1.4 template we used intval
				'is_module' => false,
			);
		}
		return $carriers;
	}

	public function simulateCarrierSelectedOutput()
	{
		$delivery_option = $this->getDeliveryOption();

		if (count($delivery_option) > 1 || empty($delivery_option))
			return 0;

		return Cart::intifier(reset($delivery_option));
	}

	/**
	 * Translate a string option_delivery identifier ('24,3,') in a int (3240002000)
	 *
	 * The  option_delivery identifier is a list of integers separated by a ','.
	 * This method replace the delimiter by a sequence of '0'.
	 * The size of this sequence is fixed by the first digit of the return
	 *
	 * @return int
	 */
	public static function intifier($string, $delimiter = ',')
	{
		$elm = explode($delimiter, $string);
		$max = max($elm);
		return strlen($max).join(str_repeat('0', strlen($max) + 1), $elm);
	}

	/**
	 * Translate a int option_delivery identifier (3240002000) in a string ('24,3,')
	 */
	public static function desintifier($int, $delimiter = ',')
	{
		$delimiter_len = $int[0];
		$int = substr($int, 1);
		$elm = explode(str_repeat('0', $delimiter_len + 1), $int);
		return join($delimiter, $elm);
	}

	/**
	 * Does the cart use multiple address
	 * @return boolean
	 */
	public function isMultiAddressDelivery()
	{
		static $cache = null;

		if (is_null($cache))
		{
			$sql = new DbQuery();
			$sql->select('count(distinct id_address_delivery)');
			$sql->from('cart_product', 'cp');
			$sql->where('id_cart = '.(int)$this->id);
	
			$cache = Db::getInstance()->getValue($sql) > 1;
		}
		return $cache;
	}

	/**
	 * Get all delivery addresses object for the current cart
	 */
	public function getAddressCollection()
	{
		$collection = array();
		$result = Db::getInstance()->executeS(
			'SELECT DISTINCT `id_address_delivery`
			FROM `'._DB_PREFIX_.'cart_product`
			WHERE id_cart = '.(int)$this->id
		);

		$result[] = array('id_address_delivery' => (int)$this->id_address_delivery);

		foreach ($result as $row)
			if ((int)$row['id_address_delivery'] != 0)
				$collection[(int)$row['id_address_delivery']] = new Address((int)$row['id_address_delivery']);

		return $collection;
	}

	/**
	* Set the delivery option and id_carrier, if there is only one carrier
	*/
	public function setDeliveryOption($delivery_option = null)
	{
		if (empty($delivery_option) || count($delivery_option) == 0)
		{
			$this->delivery_option = '';
			$this->id_carrier = 0;
			return;
		}

		$delivery_option_list = $this->getDeliveryOptionList(null, true);

		foreach ($delivery_option_list as $id_address => $options)
			if (!isset($delivery_option[$id_address]))
				foreach ($options as $key => $option)
					if ($option['is_best_price'])
					{
						$delivery_option[$id_address] = $key;
						break;
					}

		if (count($delivery_option) == 1)
			$this->id_carrier = $this->getIdCarrierFromDeliveryOption($delivery_option);

		$this->delivery_option = serialize($delivery_option);
	}

	protected function getIdCarrierFromDeliveryOption($delivery_option)
	{
		$delivery_option_list = $this->getDeliveryOptionList();
		foreach ($delivery_option as $key => $value)
			if (isset($delivery_option_list[$key]) && isset($delivery_option_list[$key][$value]))
				if (count($delivery_option_list[$key][$value]['carrier_list']) == 1)
					return current(array_keys($delivery_option_list[$key][$value]['carrier_list']));

		return 0;
	}

	/**
	* Get the delivery option seleted, or if no delivery option was selected, the cheapest option for each address
	* @return array delivery option
	*/
	public function getDeliveryOption($default_country = null, $dontAutoSeletectOptions = false)
	{
		$delivery_option_list = $this->getDeliveryOptionList($default_country);

		// The delivery option was selected
		if (isset($this->delivery_option) && $this->delivery_option != '')
		{
			$delivery_option = unserialize($this->delivery_option);
			$validated = true;
			foreach ($delivery_option as $id_address => $key)
				if (!isset($delivery_option_list[$id_address][$key]))
				{
					$validated = false;
					break;
				}

			if ($validated)
				return $delivery_option;
		}
		
		if ($dontAutoSeletectOptions)
			return false;

		// No delivery option selected or delivery option selected is not valid, get the better for all options
		$delivery_option = array();
		foreach ($delivery_option_list as $id_address => $options)
			foreach ($options as $key => $option)
				if ($option['is_best_price'])
				{
					$delivery_option[$id_address] = $key;
					break;
				}

		return $delivery_option;
	}

	/**
	* Return shipping total for the cart
	*
	* @param array $delivery_option Array of the delivery option for each address
	* @param booleal $use_tax
	* @param Country $default_country
	* @return float Shipping total
	*/
	public function getTotalShippingCost($delivery_option = null, $use_tax = true, Country $default_country = null)
	{
		if (is_null($delivery_option))
			$delivery_option = $this->getDeliveryOption($default_country);

		$total_shipping = 0;
		$delivery_option_list = $this->getDeliveryOptionList();
		foreach ($delivery_option as $id_address => $key)
		{
			if (!isset($delivery_option_list[$id_address]) || !isset($delivery_option_list[$id_address][$key]))
				continue;
			if ($use_tax)
				$total_shipping += $delivery_option_list[$id_address][$key]['total_price_with_tax'];
			else
				$total_shipping += $delivery_option_list[$id_address][$key]['total_price_without_tax'];
		}

		return $total_shipping;
	}
	/**
	* Return shipping total of a specific carriers for the cart
	*
	* @param int $id_carrier
	* @param array $delivery_option Array of the delivery option for each address
	* @param booleal $useTax
	* @param Country $default_country
	* @return float Shipping total
	*/
	public function getCarrierCost($id_carrier, $useTax = true, Country $default_country = null, $delivery_option = null)
	{
		if (is_null($delivery_option))
			$delivery_option = $this->getDeliveryOption($default_country);

		$total_shipping = 0;
		$delivery_option_list = $this->getDeliveryOptionList();


		foreach ($delivery_option as $id_address => $key)
		{
			if (!isset($delivery_option_list[$id_address]) || !isset($delivery_option_list[$id_address][$key]))
				continue;
			if (isset($delivery_option_list[$id_address][$key]['carrier_list'][$id_carrier]))
			{
				if ($useTax)
					$total_shipping += $delivery_option_list[$id_address][$key]['carrier_list'][$id_carrier]['total_price_with_tax'];
				else
					$total_shipping += $delivery_option_list[$id_address][$key]['carrier_list'][$id_carrier]['total_price_without_tax'];
			}
		}

		return $total_shipping;
	}


	/**
	 * @deprecated 1.5.0, use Cart->getPackageShippingCost()
	 */
	public function getOrderShippingCost($id_carrier = null, $use_tax = true, Country $default_country = null, $product_list = null)
	{
		Tools::displayAsDeprecated();
		return $this->getPackageShippingCost($id_carrier, $use_tax, $default_country, $product_list);
	}

	/**
	 * Return package shipping cost
	 *
	 * @param integer $id_carrier Carrier ID (default : current carrier)
	 * @param booleal $use_tax
	 * @param Country $default_country
	 * @param Array $product_list
	 * @param array $product_list List of product concerned by the shipping. If null, all the product of the cart are used to calculate the shipping cost
	 *
	 * @return float Shipping total
	 */
	public function getPackageShippingCost($id_carrier = null, $use_tax = true, Country $default_country = null, $product_list = null)
	{
		if ($this->isVirtualCart())
			return 0;

		if (!$default_country)
			$default_country = Context::getContext()->country;

		$complete_product_list = $this->getProducts();
		if (is_null($product_list))
			$products = $complete_product_list;
		else
			$products = $product_list;

		if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_invoice')
			$address_id = (int)$this->id_address_invoice;
		elseif (count($product_list))
		{
			$prod = current($product_list);
			$address_id = (int)$prod['id_address_delivery'];
		}
		else
			$address_id = null;
		if (!Address::addressExists($address_id))
			$address_id = null;

		// Order total in default currency without fees
		$order_total = $this->getOrderTotal(true, Cart::ONLY_PHYSICAL_PRODUCTS_WITHOUT_SHIPPING, $product_list);

		// Start with shipping cost at 0
		$shipping_cost = 0;

		// If no product added, return 0
		if ($order_total <= 0
			&& (
				!(Cart::getNbProducts($this->id) && is_null($product_list))
				||
				(count($product_list) && !is_null($product_list))
		))
			return $shipping_cost;

		// Get id zone
		if (!$this->isMultiAddressDelivery()
			&& isset($this->id_address_delivery) // Be carefull, id_address_delivery is not usefull one 1.5
			&& $this->id_address_delivery
			&& Customer::customerHasAddress($this->id_customer, $this->id_address_delivery
		))
			$id_zone = Address::getZoneById((int)$this->id_address_delivery);
		else
		{
			if (!Validate::isLoadedObject($default_country))
				$default_country = new Country(Configuration::get('PS_COUNTRY_DEFAULT'), Configuration::get('PS_LANG_DEFAULT'));

			$id_zone = (int)$default_country->id_zone;
		}

		if ($id_carrier && !$this->isCarrierInRange((int)$id_carrier, (int)$id_zone))
			$id_carrier = '';

		if (empty($id_carrier) && $this->isCarrierInRange((int)Configuration::get('PS_CARRIER_DEFAULT'), (int)$id_zone))
			$id_carrier = (int)Configuration::get('PS_CARRIER_DEFAULT');

		if (empty($id_carrier))
		{
			if ((int)$this->id_customer)
			{
				$customer = new Customer((int)$this->id_customer);
				$result = Carrier::getCarriers((int)Configuration::get('PS_LANG_DEFAULT'), true, false, (int)$id_zone, $customer->getGroups());
				unset($customer);
			}
			else
				$result = Carrier::getCarriers((int)Configuration::get('PS_LANG_DEFAULT'), true, false, (int)$id_zone);

			foreach ($result as $k => $row)
			{
				if ($row['id_carrier'] == Configuration::get('PS_CARRIER_DEFAULT'))
					continue;

				if (!isset(self::$_carriers[$row['id_carrier']]))
					self::$_carriers[$row['id_carrier']] = new Carrier((int)$row['id_carrier']);

				$carrier = self::$_carriers[$row['id_carrier']];

				// Get only carriers that are compliant with shipping method
				if (($carrier->getShippingMethod() == Carrier::SHIPPING_METHOD_WEIGHT && $carrier->getMaxDeliveryPriceByWeight((int)$id_zone) === false)
				|| ($carrier->getShippingMethod() == Carrier::SHIPPING_METHOD_PRICE && $carrier->getMaxDeliveryPriceByPrice((int)$id_zone) === false))
				{
					unset($result[$k]);
					continue;
				}

				// If out-of-range behavior carrier is set on "Desactivate carrier"
				if ($row['range_behavior'])
				{
					$check_delivery_price_by_weight = Carrier::checkDeliveryPriceByWeight($row['id_carrier'], $this->getTotalWeight(), (int)$id_zone);

					$total_order = $this->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING, $product_list);
					$check_delivery_price_by_price = Carrier::checkDeliveryPriceByPrice($row['id_carrier'], $total_order, (int)$id_zone, (int)$this->id_currency);

					// Get only carriers that have a range compatible with cart
					if (($carrier->getShippingMethod() == Carrier::SHIPPING_METHOD_WEIGHT && !$check_delivery_price_by_weight)
					|| ($carrier->getShippingMethod() == Carrier::SHIPPING_METHOD_PRICE && !$check_delivery_price_by_price))
					{
						unset($result[$k]);
						continue;
					}
				}

				if ($carrier->getShippingMethod() == Carrier::SHIPPING_METHOD_WEIGHT)
					$shipping = $carrier->getDeliveryPriceByWeight($this->getTotalWeight($product_list), (int)$id_zone);
				else
					$shipping = $carrier->getDeliveryPriceByPrice($order_total, (int)$id_zone, (int)$this->id_currency);

				if (!isset($min_shipping_price))
					$min_shipping_price = $shipping;

				if ($shipping <= $min_shipping_price)
				{
					$id_carrier = (int)$row['id_carrier'];
					$min_shipping_price = $shipping;
				}
			}
		}

		if (empty($id_carrier))
			$id_carrier = Configuration::get('PS_CARRIER_DEFAULT');

		if (!isset(self::$_carriers[$id_carrier]))
			self::$_carriers[$id_carrier] = new Carrier((int)$id_carrier, Configuration::get('PS_LANG_DEFAULT'));

		$carrier = self::$_carriers[$id_carrier];

		if (!Validate::isLoadedObject($carrier))
			die(Tools::displayError('Fatal error: "no default carrier"'));

		if (!$carrier->active)
			return $shipping_cost;

		// Free fees if free carrier
		if ($carrier->is_free == 1)
			return 0;

		// Select carrier tax
		if ($use_tax && !Tax::excludeTaxeOption())
			$carrier_tax = $carrier->getTaxesRate(new Address((int)$address_id));

		$configuration = Configuration::getMultiple(array(
			'PS_SHIPPING_FREE_PRICE',
			'PS_SHIPPING_HANDLING',
			'PS_SHIPPING_METHOD',
			'PS_SHIPPING_FREE_WEIGHT'
		));

		// Free fees
		$free_fees_price = 0;
		if (isset($configuration['PS_SHIPPING_FREE_PRICE']))
			$free_fees_price = Tools::convertPrice((float)$configuration['PS_SHIPPING_FREE_PRICE'], Currency::getCurrencyInstance((int)$this->id_currency));
		$orderTotalwithDiscounts = $this->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING);
		if ($orderTotalwithDiscounts >= (float)($free_fees_price) && (float)($free_fees_price) > 0)
			return $shipping_cost;

		if (isset($configuration['PS_SHIPPING_FREE_WEIGHT'])
			&& $this->getTotalWeight() >= (float)$configuration['PS_SHIPPING_FREE_WEIGHT']
			&& (float)$configuration['PS_SHIPPING_FREE_WEIGHT'] > 0)
			return $shipping_cost;

		// Get shipping cost using correct method
		if ($carrier->range_behavior)
		{
			// Get id zone
			if (isset($this->id_address_delivery)
				&& $this->id_address_delivery
				&& Customer::customerHasAddress($this->id_customer, $this->id_address_delivery))
				$id_zone = Address::getZoneById((int)$this->id_address_delivery);
			else
				$id_zone = (int)$default_country->id_zone;

			$check_delivery_price_by_weight = Carrier::checkDeliveryPriceByWeight((int)$carrier->id, $this->getTotalWeight(), (int)$id_zone);

			// Code Review V&V TO FINISH
			$check_delivery_price_by_price = Carrier::checkDeliveryPriceByPrice(
				$carrier->id,
				$this->getOrderTotal(
					true,
					Cart::BOTH_WITHOUT_SHIPPING,
					$product_list
				),
				$id_zone,
				(int)$this->id_currency
			);

			if ((
					$carrier->getShippingMethod() == Carrier::SHIPPING_METHOD_WEIGHT
					&& !$check_delivery_price_by_weight
				) || (
					$carrier->getShippingMethod() == Carrier::SHIPPING_METHOD_PRICE
					&& !$check_delivery_price_by_price
			))
				$shipping_cost += 0;
			else
			{
				if ($carrier->getShippingMethod() == Carrier::SHIPPING_METHOD_WEIGHT)
					$shipping_cost += $carrier->getDeliveryPriceByWeight($this->getTotalWeight($product_list), $id_zone);
				else // by price
					$shipping_cost += $carrier->getDeliveryPriceByPrice($order_total, $id_zone, (int)$this->id_currency);
			}
		}
		else
		{
			if ($carrier->getShippingMethod() == Carrier::SHIPPING_METHOD_WEIGHT)
				$shipping_cost += $carrier->getDeliveryPriceByWeight($this->getTotalWeight($product_list), $id_zone);
			else
				$shipping_cost += $carrier->getDeliveryPriceByPrice($order_total, $id_zone, (int)$this->id_currency);

		}
		// Adding handling charges
		if (isset($configuration['PS_SHIPPING_HANDLING']) && $carrier->shipping_handling)
			$shipping_cost += (float)$configuration['PS_SHIPPING_HANDLING'];

		// Additional Shipping Cost per product
		foreach ($products as $product)
			$shipping_cost += $product['additional_shipping_cost'] * $product['cart_quantity'];

		$shipping_cost = Tools::convertPrice($shipping_cost, Currency::getCurrencyInstance((int)$this->id_currency));

		//get external shipping cost from module
		if ($carrier->shipping_external)
		{
			$module_name = $carrier->external_module_name;
			$module = Module::getInstanceByName($module_name);

			if (Validate::isLoadedObject($module))
			{
				if (array_key_exists('id_carrier', $module))
					$module->id_carrier = $carrier->id;
				if ($carrier->need_range)
					if (method_exists($module, 'getPackageShippingCost'))
						$shipping_cost = $module->getPackageShippingCost($this, $shipping_cost, $products);
					else
						$shipping_cost = $module->getOrderShippingCost($this, $shipping_cost);
				else
					$shipping_cost = $module->getOrderShippingCostExternal($this);

				// Check if carrier is available
				if ($shipping_cost === false)
					return false;
			}
			else
				return false;
		}

		// Apply tax
		if (isset($carrier_tax))
			$shipping_cost *= 1 + ($carrier_tax / 100);

		return (float)Tools::ps_round((float)$shipping_cost, 2);
	}

	/**
	* Return cart weight
	* @return float Cart weight
	*/
	public function getTotalWeight($products = null)
	{
		if (!is_null($products))
		{
			$total_weight = 0;
			foreach ($products as $product)
			{
				if (is_null($product['weight_attribute']))
					$total_weight += $product['weight'] * $product['cart_quantity'];
				else
					$total_weight += $product['weight_attribute'] * $product['cart_quantity'];
			}
			return $total_weight;
		}

		if (!isset(self::$_totalWeight[$this->id]))
		{
			if (Combination::isFeatureActive())
				$weight_product_with_attribute = Db::getInstance()->getValue('
				SELECT SUM((p.`weight` + pa.`weight`) * cp.`quantity`) as nb
				FROM `'._DB_PREFIX_.'cart_product` cp
				LEFT JOIN `'._DB_PREFIX_.'product` p ON (cp.`id_product` = p.`id_product`)
				LEFT JOIN `'._DB_PREFIX_.'product_attribute` pa ON (cp.`id_product_attribute` = pa.`id_product_attribute`)
				WHERE (cp.`id_product_attribute` IS NOT NULL AND cp.`id_product_attribute` != 0)
				AND cp.`id_cart` = '.(int)$this->id);
			else
				$weight_product_with_attribute = 0;

			$weight_product_without_attribute = Db::getInstance()->getValue('
			SELECT SUM(p.`weight` * cp.`quantity`) as nb
			FROM `'._DB_PREFIX_.'cart_product` cp
			LEFT JOIN `'._DB_PREFIX_.'product` p ON (cp.`id_product` = p.`id_product`)
			WHERE (cp.`id_product_attribute` IS NULL OR cp.`id_product_attribute` = 0)
			AND cp.`id_cart` = '.(int)$this->id);

			self::$_totalWeight[$this->id] = round((float)$weight_product_with_attribute + (float)$weight_product_without_attribute, 3);
		}

		return self::$_totalWeight[$this->id];
	}

	/**
	 * @deprecated 1.5.0
	 */
	public function checkDiscountValidity($obj, $discounts, $order_total, $products, $check_cart_discount = false)
	{
		Tools::displayAsDeprecated();
		$context = Context::getContext()->cloneContext();
		$context->cart = $this;

		return $obj->checkValidity($context);
	}

	/**
	* Return useful informations for cart
	*
	* @return array Cart details
	*/
	public function getSummaryDetails($id_lang = null)
	{
		if (!$id_lang)
			$id_lang = Context::getContext()->language->id;

		$delivery = new Address((int)$this->id_address_delivery);
		$invoice = new Address((int)$this->id_address_invoice);

		// New layout system with personalization fields
		$formatted_addresses['invoice'] = AddressFormat::getFormattedLayoutData($invoice);
		$formatted_addresses['delivery'] = AddressFormat::getFormattedLayoutData($delivery);

		$total_tax = $this->getOrderTotal() - $this->getOrderTotal(false);

		if ($total_tax < 0)
			$total_tax = 0;

		return array(
			'delivery' => $delivery,
			'delivery_state' => State::getNameById($delivery->id_state),
			'invoice' => $invoice,
			'invoice_state' => State::getNameById($invoice->id_state),
			'formattedAddresses' => $formatted_addresses,
			'products' => $this->getProducts(false),
			'discounts' => $this->getCartRules(),
			'is_virtual_cart' => (int)$this->isVirtualCart(),
			'total_discounts' => $this->getOrderTotal(true, Cart::ONLY_DISCOUNTS),
			'total_discounts_tax_exc' => $this->getOrderTotal(false, Cart::ONLY_DISCOUNTS),
			'total_wrapping' => $this->getOrderTotal(true, Cart::ONLY_WRAPPING),
			'total_wrapping_tax_exc' => $this->getOrderTotal(false, Cart::ONLY_WRAPPING),
			'total_shipping' => $this->getTotalShippingCost(),
			'total_shipping_tax_exc' => $this->getTotalShippingCost(null, false),
			'total_products_wt' => $this->getOrderTotal(true, Cart::ONLY_PRODUCTS),
			'total_products' => $this->getOrderTotal(false, Cart::ONLY_PRODUCTS),
			'total_price' => $this->getOrderTotal(),
			'total_tax' => $total_tax,
			'total_price_without_tax' => $this->getOrderTotal(false),
			'is_multi_address_delivery' => $this->isMultiAddressDelivery() || ((int)Tools::getValue('multi-shipping') == 1),
			'free_ship' => 0,
			'carrier' => new Carrier($this->id_carrier, $id_lang),
		);
	}

	public function checkQuantities()
	{
		if (Configuration::get('PS_CATALOG_MODE'))
			return false;

		foreach ($this->getProducts() as $product)
			if (!$product['active']
				|| (
					!$product['allow_oosp'] && $product['stock_quantity'] < $product['cart_quantity']
				)
				|| !$product['available_for_order'])
				return false;

		return true;
	}

	public static function lastNoneOrderedCart($id_customer)
	{
		$sql = 'SELECT c.`id_cart`
				FROM '._DB_PREFIX_.'cart c
				LEFT JOIN '._DB_PREFIX_.'orders o ON (c.`id_cart` = o.`id_cart`)
				WHERE c.`id_customer` = '.(int)$id_customer.'
					AND o.`id_cart` IS NULL
					'.Shop::addSqlRestriction(Shop::SHARE_ORDER, 'c').'
				ORDER BY c.`date_upd` DESC';

		if (!$id_cart = Db::getInstance()->getValue($sql))
			return false;

		return $id_cart;
	}

	/**
	* Check if cart contains only virtual products
	*
	* @return boolean true if is a virtual cart or false
	*/
	public function isVirtualCart($strict = false)
	{
		if (!ProductDownload::isFeatureActive())
			return false;

		if (!isset(self::$_isVirtualCart[$this->id]))
		{
			$products = $this->getProducts();
			if (!count($products))
				return false;

			$is_virtual = 1;
			foreach ($products as $product)
			{
				if (empty($product['is_virtual']))
					$is_virtual = 0;
			}
			self::$_isVirtualCart[$this->id] = (int)$is_virtual;
		}

		return self::$_isVirtualCart[$this->id];
	}

	/**
	 * Build cart object from provided id_order
	 *
	 * @param int $id_order
	 * @return Cart|bool
	 */
	public static function getCartByOrderId($id_order)
	{
		if ($id_cart = Cart::getCartIdByOrderId($id_order))
			return new Cart((int)$id_cart);

		return false;
	}

	public static function getCartIdByOrderId($id_order)
	{
		$result = Db::getInstance()->getRow('SELECT `id_cart` FROM '._DB_PREFIX_.'orders WHERE `id_order` = '.(int)$id_order);
		if (!$result || empty($result) || !key_exists('id_cart', $result))
			return false;
		return $result['id_cart'];
	}

	/**
	 * Add customer's text
	 *
	 * @params int $id_product
	 * @params int $index
	 * @params int $type
	 * @params string $textValue
	 *
	 * @return bool Always true
	 */
	public function addTextFieldToProduct($id_product, $index, $type, $text_value)
	{
		$text_value = str_replace(array("\n", "\r"), '', nl2br($text_value));
		$text_value = str_replace('\\', '\\\\', $text_value);
		$text_value = str_replace('\'', '\\\'', $text_value);
		return $this->_addCustomization($id_product, 0, $index, $type, $text_value, 0);
	}

	/**
	 * Add customer's pictures
	 *
	 * @return bool Always true
	 */
	public function addPictureToProduct($id_product, $index, $type, $file)
	{
		return $this->_addCustomization($id_product, 0, $index, $type, $file, 0);
	}

	/**
	 * Remove a customer's customization
	 *
	 * @param int $id_product
	 * @param int $index
	 * @return bool
	 */
	public function deleteCustomizationToProduct($id_product, $index)
	{
		$result = true;

		$cust_data = Db::getInstance()->getRow('
			SELECT cu.`id_customization`, cd.`index`, cd.`value`, cd.`type` FROM `'._DB_PREFIX_.'customization` cu
			LEFT JOIN `'._DB_PREFIX_.'customized_data` cd
			ON cu.`id_customization` = cd.`id_customization`
			WHERE cu.`id_cart` = '.(int)$this->id.'
			AND cu.`id_product` = '.(int)$id_product.'
			AND `index` = '.(int)$index.'
			AND `in_cart` = 0'
		);

		// Delete customization picture if necessary
		if ($cust_data['type'] == 0)
			$result &= (@unlink(_PS_UPLOAD_DIR_.$cust_data['value']) && @unlink(_PS_UPLOAD_DIR_.$cust_data['value'].'_small'));

		$result &= Db::getInstance()->execute('DELETE
			FROM `'._DB_PREFIX_.'customized_data`
			WHERE `id_customization` = '.(int)$cust_data['id_customization'].'
			AND `index` = '.(int)$index
		);
		return $result;
	}

	/**
	 * Return custom pictures in this cart for a specified product
	 *
	 * @param int $id_product
	 * @param int $type only return customization of this type
	 * @param bool $not_in_cart only return customizations that are not in cart already
	 * @return array result rows
	 */
	public function getProductCustomization($id_product, $type = null, $not_in_cart = false)
	{
		if (!Customization::isFeatureActive())
			return array();

		$result = Db::getInstance()->executeS('
			SELECT cu.id_customization, cd.index, cd.value, cd.type, cu.in_cart, cu.quantity
			FROM `'._DB_PREFIX_.'customization` cu
			LEFT JOIN `'._DB_PREFIX_.'customized_data` cd ON (cu.`id_customization` = cd.`id_customization`)
			WHERE cu.id_cart = '.(int)$this->id.'
			AND cu.id_product = '.(int)$id_product.
			($type === Product::CUSTOMIZE_FILE ? ' AND type = '.(int)Product::CUSTOMIZE_FILE : '').
			($type === Product::CUSTOMIZE_TEXTFIELD ? ' AND type = '.(int)Product::CUSTOMIZE_TEXTFIELD : '').
			($not_in_cart ? ' AND in_cart = 0' : '')
		);
		return $result;
	}

	public static function getCustomerCarts($id_customer)
	{
		$result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
			SELECT *
			FROM '._DB_PREFIX_.'cart c
			WHERE c.`id_customer` = '.(int)$id_customer.'
			ORDER BY c.`date_add` DESC');
		return $result;
	}

	public static function replaceZeroByShopName($echo, $tr)
	{
		return ($echo == '0' ? Configuration::get('PS_SHOP_NAME') : $echo);
	}

	public function duplicate()
	{
		if (!Validate::isLoadedObject($this))
			return false;

		$cart = new Cart($this->id);
		$cart->id = null;
		$cart->id_shop = $this->id_shop;
		$cart->id_shop_group = $this->id_shop_group;
		$cart->add();

		if (!Validate::isLoadedObject($cart))
			return false;

		$success = true;
		$products = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('SELECT * FROM `'._DB_PREFIX_.'cart_product` WHERE `id_cart` = '.(int)$this->id);

		foreach ($products as $product)
			$success &= $cart->updateQty(
				$product['quantity'],
				(int)$product['id_product'],
				(int)$product['id_product_attribute'],
				null,
				(int)$product['id_address_delivery'],
				'up',
				new Shop($cart->id_shop)
			);

		// Customized products
		$customs = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
			SELECT *
			FROM '._DB_PREFIX_.'customization c
			LEFT JOIN '._DB_PREFIX_.'customized_data cd ON cd.id_customization = c.id_customization
			WHERE c.id_cart = '.(int)$this->id
		);

		// Get datas from customization table
		$customs_by_id = array();
		foreach ($customs as $custom)
		{
			if (!isset($customs_by_id[$custom['id_customization']]))
				$customs_by_id[$custom['id_customization']] = array(
					'id_product_attribute' => $custom['id_product_attribute'],
					'id_product' => $custom['id_product'],
					'quantity' => $custom['quantity']
				);
		}

		// Insert new customizations
		$custom_ids = array();
		foreach ($customs_by_id as $customization_id => $val)
		{
			Db::getInstance()->execute('
				INSERT INTO `'._DB_PREFIX_.'customization` (id_cart, id_product_attribute, id_product, quantity)
				VALUES('.(int)$cart->id.', '.(int)$val['id_product_attribute'].', '.(int)$val['id_product'].', '.(int)$val['quantity'].')'
			);
			$custom_ids[$customization_id] = Db::getInstance(_PS_USE_SQL_SLAVE_)->Insert_ID();
		}

		// Insert customized_data
		if (count($customs))
		{
			$first = true;
			$sql_custom_data = 'INSERT INTO '._DB_PREFIX_.'customized_data (`id_customization`, `type`, `index`, `value`) VALUES ';
			foreach ($customs as $custom)
			{
				if (!$first)
					$sql_custom_data .= ',';
				else
					$first = false;

				$sql_custom_data .= '('.(int)$custom_ids[$custom['id_customization']].', '.(int)$custom['type'].', '.
					(int)$custom['index'].', \''.pSQL($custom['value']).'\')';
			}
			Db::getInstance()->execute($sql_custom_data);
		}

		return array('cart' => $cart, 'success' => $success);
	}

	public function getWsCartRows()
	{
		$query = '
			SELECT id_product, id_product_attribute, quantity
			FROM `'._DB_PREFIX_.'cart_product`
			WHERE id_cart = '.(int)$this->id;

		$result = Db::getInstance()->executeS($query);
		return $result;
	}

	public function setWsCartRows($values)
	{
		if ($this->deleteAssociations())
		{
			$query = 'INSERT INTO `'._DB_PREFIX_.'cart_product`(`id_cart`, `id_product`, `id_product_attribute`, `quantity`, `date_add`) VALUES ';

			foreach ($values as $value)
				$query .= '('.(int)$this->id.', '.(int)$value['id_product'].', '.
					(isset($value['id_product_attribute']) ? (int)$value['id_product_attribute'] : 'NULL').', '.(int)$value['quantity'].', NOW()),';

			Db::getInstance()->execute(rtrim($query, ','));
		}

		return true;
	}

	public function setProductAddressDelivery($id_product, $id_product_attribute, $old_id_address_delivery, $new_id_address_delivery)
	{
		// Check address is linked with the customer
		if (!Customer::customerHasAddress(Context::getContext()->customer->id, $new_id_address_delivery))
			return false;

		if ($new_id_address_delivery == $old_id_address_delivery)
			return false;

		// Checking if the product with the old address delivery exists
		$sql = new DbQuery();
		$sql->select('count(*)');
		$sql->from('cart_product', 'cp');
		$sql->where('id_product = '.(int)$id_product);
		$sql->where('id_product_attribute = '.(int)$id_product_attribute);
		$sql->where('id_address_delivery = '.(int)$old_id_address_delivery);
		$sql->where('id_cart = '.(int)$this->id);
		$result = Db::getInstance()->getValue($sql);

		if ($result == 0)
			return false;

		// Checking if there is no others similar products with this new address delivery
		$sql = new DbQuery();
		$sql->select('sum(quantity) as qty');
		$sql->from('cart_product', 'cp');
		$sql->where('id_product = '.(int)$id_product);
		$sql->where('id_product_attribute = '.(int)$id_product_attribute);
		$sql->where('id_address_delivery = '.(int)$new_id_address_delivery);
		$sql->where('id_cart = '.(int)$this->id);
		$result = Db::getInstance()->getValue($sql);

		// Removing similar products with this new address delivery
		$sql = 'DELETE FROM '._DB_PREFIX_.'cart_product
			WHERE id_product = '.(int)$id_product.'
			AND id_product_attribute = '.(int)$id_product_attribute.'
			AND id_address_delivery = '.(int)$new_id_address_delivery.'
			AND id_cart = '.(int)$this->id.'
			LIMIT 1';
		Db::getInstance()->execute($sql);

		// Changing the address
		$sql = 'UPDATE '._DB_PREFIX_.'cart_product
			SET `id_address_delivery` = '.(int)$new_id_address_delivery.',
			`quantity` = `quantity` + '.(int)$result['sum'].'
			WHERE id_product = '.(int)$id_product.'
			AND id_product_attribute = '.(int)$id_product_attribute.'
			AND id_address_delivery = '.(int)$old_id_address_delivery.'
			AND id_cart = '.(int)$this->id.'
			LIMIT 1';
		Db::getInstance()->execute($sql);

		// Changing the address of the customizations
		$sql = 'UPDATE '._DB_PREFIX_.'customization
			SET `id_address_delivery` = '.(int)$new_id_address_delivery.'
			WHERE id_product = '.(int)$id_product.'
			AND id_product_attribute = '.(int)$id_product_attribute.'
			AND id_address_delivery = '.(int)$old_id_address_delivery.'
			AND id_cart = '.(int)$this->id;
		Db::getInstance()->execute($sql);

		return true;
	}

	public function duplicateProduct($id_product, $id_product_attribute, $id_address_delivery,
		$new_id_address_delivery, $quantity = 1, $keep_quantity = false)
	{
		// Check address is linked with the customer
		if (!Customer::customerHasAddress(Context::getContext()->customer->id, $new_id_address_delivery))
			return false;

		// Checking the product do not exist with the new address
		$sql = new DbQuery();
		$sql->select('count(*)');
		$sql->from('cart_product', 'c');
		$sql->where('id_product = '.(int)$id_product);
		$sql->where('id_product_attribute = '.(int)$id_product_attribute);
		$sql->where('id_address_delivery = '.(int)$new_id_address_delivery);
		$sql->where('id_cart = '.(int)$this->id);
		$result = Db::getInstance()->getValue($sql);

		if ($result > 0)
			return false;

		// Duplicating cart_product line
		$sql = 'INSERT INTO '._DB_PREFIX_.'cart_product
			(`id_cart`, `id_product`, `id_shop`, `id_product_attribute`, `quantity`, `date_add`, `id_address_delivery`)
			values(
				'.(int)$this->id.',
				'.(int)$id_product.',
				'.(int)$this->id_shop.',
				'.(int)$id_product_attribute.',
				'.(int)$quantity.',
				NOW(),
				'.(int)$new_id_address_delivery.')';

		Db::getInstance()->execute($sql);

		if (!$keep_quantity)
		{
			$sql = new DbQuery();
			$sql->select('quantity');
			$sql->from('cart_product', 'c');
			$sql->where('id_product = '.(int)$id_product);
			$sql->where('id_product_attribute = '.(int)$id_product_attribute);
			$sql->where('id_address_delivery = '.(int)$id_address_delivery);
			$sql->where('id_cart = '.(int)$this->id);
			$duplicatedQuantity = Db::getInstance()->getValue($sql);

			if ($duplicatedQuantity > $quantity)
			{
				$sql = 'UPDATE '._DB_PREFIX_.'cart_product
					SET `quantity` = `quantity` - '.(int)$quantity.'
					WHERE id_cart = '.(int)$this->id.'
					AND id_product = '.(int)$id_product.'
					AND id_shop = '.(int)$this->id_shop.'
					AND id_product_attribute = '.(int)$id_product_attribute.'
					AND id_address_delivery = '.(int)$id_address_delivery;
				Db::getInstance()->execute($sql);
			}
		}

		// Checking if there is customizations
		$sql = new DbQuery();
		$sql->select('*');
		$sql->from('customization', 'c');
		$sql->where('id_product = '.(int)$id_product);
		$sql->where('id_product_attribute = '.(int)$id_product_attribute);
		$sql->where('id_address_delivery = '.(int)$id_address_delivery);
		$sql->where('id_cart = '.(int)$this->id);
		$results = Db::getInstance()->executeS($sql);

		foreach ($results as $customization)
		{

			// Duplicate customization
			$sql = 'INSERT INTO '._DB_PREFIX_.'customization
				(`id_product_attribute`, `id_address_delivery`, `id_cart`, `id_product`, `quantity`, `in_cart`)
				VALUES (
					'.$customization['id_product_attribute'].',
					'.$new_id_address_delivery.',
					'.$customization['id_cart'].',
					'.$customization['id_product'].',
					'.$quantity.',
					'.$customization['in_cart'].')';
			Db::getInstance()->execute($sql);

			$sql = 'INSERT INTO '._DB_PREFIX_.'customized_data(`id_customization`, `type`, `index`, `value`)
				(
					SELECT '.(int)Db::getInstance()->Insert_ID().' `id_customization`, `type`, `index`, `value`
					FROM customized_data
					WHERE id_customization = '.$customization['id_customization'].'
				)';
			Db::getInstance()->execute($sql);
		}

		$customization_count = count($results);
		if ($customization_count > 0)
		{
			$sql = 'UPDATE '._DB_PREFIX_.'cart_product
				SET `quantity` = `quantity` = '.(int)$customization_count * $quantity.'
				WHERE id_cart = '.(int)$this->id.'
				AND id_product = '.(int)$id_product.'
				AND id_shop = '.(int)$this->id_shop.'
				AND id_product_attribute = '.(int)$id_product_attribute.'
				AND id_address_delivery = '.(int)$new_id_address_delivery;
			Db::getInstance()->execute($sql);
		}

		return true;
	}

	/**
	 * Update products cart address delivery with the address delivery of the cart
	 */
	public function setNoMultishipping()
	{
		// Upgrading quantities
		$sql = 'SELECT sum(`quantity`) as quantity, id_product, id_product_attribute, count(*) as count
				FROM `'._DB_PREFIX_.'cart_product`
				WHERE `id_cart` = '.(int)$this->id.'
					AND `id_shop` = '.(int)$this->id_shop.'
				GROUP BY id_product, id_product_attribute
				HAVING count > 1';

		foreach (Db::getInstance()->executeS($sql) as $product)
		{
			$sql = 'UPDATE `'._DB_PREFIX_.'cart_product`
				SET `quantity` = '.$product['quantity'].'
				WHERE  `id_cart` = '.(int)$this->id.'
					AND `id_shop` = '.(int)$this->id_shop.'
					AND id_product = '.$product['id_product'].'
					AND id_product_attribute = '.$product['id_product_attribute'];
				Db::getInstance()->execute($sql);
		}

		// Merging multiple lines
		$sql = 'DELETE cp1
			FROM `'._DB_PREFIX_.'cart_product` cp1
				INNER JOIN `'._DB_PREFIX_.'cart_product` cp2
				ON (
					(cp1.id_cart = cp2.id_cart)
					AND (cp1.id_product = cp2.id_product)
					AND (cp1.id_product_attribute = cp2.id_product_attribute)
					AND (cp1.id_address_delivery <> cp2.id_address_delivery)
					AND (cp1.date_add > cp2.date_add)
				)';
				Db::getInstance()->execute($sql);

		// Upgrading address delivery
		$sql = 'UPDATE `'._DB_PREFIX_.'cart_product`
			SET `id_address_delivery` =
			(
				SELECT `id_address_delivery`
				FROM `'._DB_PREFIX_.'cart`
				WHERE `id_cart` = '.(int)$this->id.'
					AND `id_shop` = '.(int)$this->id_shop.'
			)
			WHERE `id_cart` = '.(int)$this->id.' AND `id_shop` = '.(int)$this->id_shop;

		Db::getInstance()->execute($sql);

		$sql = 'UPDATE `'._DB_PREFIX_.'customization`
			SET `id_address_delivery` =
			(
				SELECT `id_address_delivery`
				FROM `'._DB_PREFIX_.'cart`
				WHERE `id_cart` = '.(int)$this->id.'
			)
			WHERE `id_cart` = '.(int)$this->id;

		Db::getInstance()->execute($sql);
	}

	/**
	 * Set an address to all products on the cart without address delivery
	 */
	public function autosetProductAddress()
	{
		$id_address_delivery = 0;
		// Get the main address of the customer
		if ((int)$this->id_address_delivery > 0)
			$id_address_delivery = (int)$this->id_address_delivery;
		else
			$id_address_delivery = (int)Address::getFirstCustomerAddressId(Context::getContext()->customer->id);

		if (!$id_address_delivery)
			return;

		// Update
		$sql = 'UPDATE `'._DB_PREFIX_.'cart_product`
			SET `id_address_delivery` = '.(int)$id_address_delivery.'
			WHERE `id_cart` = '.(int)$this->id.'
				AND (`id_address_delivery` = 0 OR `id_address_delivery` IS NULL)
				AND `id_shop` = '.(int)$this->id_shop;
		Db::getInstance()->execute($sql);

		$sql = 'UPDATE `'._DB_PREFIX_.'customization`
			SET `id_address_delivery` = '.(int)$id_address_delivery.'
			WHERE `id_cart` = '.(int)$this->id.'
				AND (`id_address_delivery` = 0 OR `id_address_delivery` IS NULL)';

		Db::getInstance()->execute($sql);
	}

	public function deleteAssociations()
	{
		return (Db::getInstance()->execute('
				DELETE FROM `'._DB_PREFIX_.'cart_product`
				WHERE `id_cart` = '.(int)$this->id) !== false);
	}

	/**
	 * isGuestCartByCartId
	 *
	 * @param int $id_cart
	 * @return bool true if cart has been made by a guest customer
	 */
	public static function isGuestCartByCartId($id_cart)
	{
		if (!(int)$id_cart)
			return false;
		return (bool)Db::getInstance()->getValue('
			SELECT `is_guest`
			FROM `'._DB_PREFIX_.'customer` cu
			LEFT JOIN `'._DB_PREFIX_.'cart` ca ON (ca.`id_customer` = cu.`id_customer`)
			WHERE ca.`id_cart` = '.(int)$id_cart);
	}

	/**
	 * isCarrierInRange
	 *
	 * Check if the specified carrier is in range
	 *
	 * @id_carrier int
	 * @id_zone int
	 */
	public function isCarrierInRange($id_carrier, $id_zone)
	{
		$carrier = new Carrier((int)$id_carrier, Configuration::get('PS_LANG_DEFAULT'));
		$shipping_method = $carrier->getShippingMethod();
		if (!$carrier->range_behavior)
			return true;

		if ($shipping_method == Carrier::SHIPPING_METHOD_FREE)
			return true;

		$check_delivery_price_by_weight = Carrier::checkDeliveryPriceByWeight(
			(int)$id_carrier,
			$this->getTotalWeight(),
			$id_zone
		);
		if ($shipping_method == Carrier::SHIPPING_METHOD_WEIGHT && $check_delivery_price_by_weight)
			return true;

		$check_delivery_price_by_price = Carrier::checkDeliveryPriceByPrice(
			(int)$id_carrier,
			$this->getOrderTotal(
				true,
				Cart::BOTH_WITHOUT_SHIPPING
			),
			$id_zone,
			(int)$this->id_currency
		);
		if ($shipping_method == Carrier::SHIPPING_METHOD_PRICE && $check_delivery_price_by_price)
			return true;

		return false;
	}

	/**
	 * @param bool $ignore_virtual Ignore virtual product
	 * @param bool $exclusive If true, the validation is exclusive : it must be present product in stock and out of stock
	 * @since 1.5.0
	 * 
	 * @return bool false is some products from the cart are out of stock
	 */
	public function isAllProductsInStock($ignore_virtual = false, $exclusive = false)
	{
		$product_out_of_stock = 0;
		$product_in_stock = 0;
		foreach ($this->getProducts() as $product)
		{
			if (!$exclusive)
			{
				if ((int)$product['quantity_available'] <= 0
					&& (!$ignore_virtual || !$product['is_virtual']))
					return false;
			}
			else
			{
				if ((int)$product['quantity_available'] <= 0
					&& (!$ignore_virtual || !$product['is_virtual']))
					$product_out_of_stock++;
				if ((int)$product['quantity_available'] > 0
					&& (!$ignore_virtual || !$product['is_virtual']))
					$product_in_stock++;
				
				if ($product_in_stock > 0 && $product_out_of_stock > 0)
					return false;
			}
		}
		return true;
	}
	
	/**
	 * 
	 * Execute hook displayCarrierList (extraCarrier) and merge theme to the $array
	 * @param array $array
	 */
	public static function addExtraCarriers(&$array)
	{
		$first = true;
		$hook_extracarrier_addr = array();
		foreach (Context::getContext()->cart->getAddressCollection() as $address)
		{
			$hook = Hook::exec('displayCarrierList', array('address' => $address));
			$hook_extracarrier_addr[$address->id] = $hook;
			
			if ($first)
			{
				$array = array_merge(
					$array,
					array('HOOK_EXTRACARRIER' => $hook)
				);
				$first = false;
			}
			$array = array_merge(
				$array,
				array('HOOK_EXTRACARRIER_ADDR' => $hook_extracarrier_addr)
			);
		}
	}
}
